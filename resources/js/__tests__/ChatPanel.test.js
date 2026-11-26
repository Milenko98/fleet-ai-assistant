import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ChatPanel from '../components/ChatPanel.vue';
import { api } from '../api';

beforeEach(() => {
    Element.prototype.scrollTo = vi.fn();
});

async function ask(w, text) {
    await w.find('textarea').setValue(text);
    await w.find('form').trigger('submit');
    await flushPromises();
}

describe('ChatPanel', () => {
    it('prikazuje primjere pitanja i upozorenje bez API ključa', () => {
        const w = mount(ChatPanel, { props: { claudeReady: false } });

        expect(w.text()).toContain('Što želiš znati o voznom parku?');
        expect(w.text()).toContain('ANTHROPIC_API_KEY');
        expect(w.findAll('button').length).toBeGreaterThanOrEqual(6);
    });

    it('šalje pitanje i prikazuje odgovor agenta', async () => {
        const chat = vi.spyOn(api, 'chat').mockResolvedValue({
            answer: 'Imate **8** vozila.',
            steps: [{ korak: 1, alat: 'popis_vozila', ulaz: {}, izlaz: [], greska: false, trajanje_ms: 4 }],
            usage: { input_tokens: 100, output_tokens: 10 },
        });
        const w = mount(ChatPanel, { props: { claudeReady: true } });

        await ask(w, 'Koliko imamo vozila?');

        expect(chat).toHaveBeenCalledWith([{ role: 'user', content: 'Koliko imamo vozila?' }]);
        expect(w.text()).toContain('Koliko imamo vozila?');
        expect(w.find('strong').text()).toBe('8');
        expect(w.text()).toContain('Koraci agenta');
    });

    it('šalje cijeli razgovor u sljedećem pitanju', async () => {
        const chat = vi.spyOn(api, 'chat')
            .mockResolvedValueOnce({ answer: 'Prvi odgovor', steps: [], usage: { input_tokens: 1, output_tokens: 1 } })
            .mockResolvedValueOnce({ answer: 'Drugi odgovor', steps: [], usage: { input_tokens: 1, output_tokens: 1 } });
        const w = mount(ChatPanel, { props: { claudeReady: true } });

        await ask(w, 'Prvo pitanje');
        await ask(w, 'Drugo pitanje');

        expect(chat).toHaveBeenLastCalledWith([
            { role: 'user', content: 'Prvo pitanje' },
            { role: 'assistant', content: 'Prvi odgovor' },
            { role: 'user', content: 'Drugo pitanje' },
        ]);
    });

    it('prikazuje grešku, vraća pitanje u unos i ne šalje ga u povijesti', async () => {
        const chat = vi.spyOn(api, 'chat')
            .mockRejectedValueOnce(new Error('Greška agenta: nema ključa'))
            .mockResolvedValueOnce({ answer: 'OK', steps: [], usage: { input_tokens: 1, output_tokens: 1 } });
        const w = mount(ChatPanel, { props: { claudeReady: true } });

        await ask(w, 'Neuspjelo pitanje');

        expect(w.text()).toContain('Greška agenta: nema ključa');
        expect(w.find('textarea').element.value).toBe('Neuspjelo pitanje');

        await ask(w, 'Novo pitanje');
        expect(chat).toHaveBeenLastCalledWith([{ role: 'user', content: 'Novo pitanje' }]);
    });

    it('Enter šalje, Shift+Enter ne šalje', async () => {
        const chat = vi.spyOn(api, 'chat').mockResolvedValue({ answer: 'x', steps: [], usage: { input_tokens: 1, output_tokens: 1 } });
        const w = mount(ChatPanel, { props: { claudeReady: true } });
        const textarea = w.find('textarea');

        await textarea.setValue('Pitanje');
        await textarea.trigger('keydown', { key: 'Enter', shiftKey: true });
        expect(chat).not.toHaveBeenCalled();

        await textarea.trigger('keydown', { key: 'Enter' });
        await flushPromises();
        expect(chat).toHaveBeenCalledOnce();
    });
});
