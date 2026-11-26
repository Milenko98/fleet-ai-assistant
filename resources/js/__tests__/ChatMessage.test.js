import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ChatMessage from '../components/ChatMessage.vue';

describe('ChatMessage', () => {
    it('prikazuje korisnikovo pitanje kao običan tekst', () => {
        const w = mount(ChatMessage, { props: { message: { role: 'user', content: '**nije markdown**' } } });

        expect(w.text()).toContain('**nije markdown**');
        expect(w.find('strong').exists()).toBe(false);
    });

    it('renderira markdown odgovor agenta s tablicom', () => {
        const content = 'Vozila:\n\n| Registracija | km |\n|---|---|\n| ZG-1234-AB | 250 |';
        const w = mount(ChatMessage, { props: { message: { role: 'assistant', content } } });

        expect(w.find('table').exists()).toBe(true);
        expect(w.find('td').text()).toBe('ZG-1234-AB');
    });

    it('uklanja opasan HTML iz odgovora (XSS)', () => {
        const content = 'Bok <img src=x onerror="alert(1)"><script>alert(2)</script>';
        const w = mount(ChatMessage, { props: { message: { role: 'assistant', content } } });

        expect(w.html()).not.toContain('onerror');
        expect(w.html()).not.toContain('<script');
    });

    it('prikazuje broj alata i tokene', () => {
        const message = {
            role: 'assistant',
            content: 'Gotovo',
            steps: [{ korak: 1, alat: 'popis_vozila', ulaz: {}, izlaz: [], greska: false, trajanje_ms: 5 }],
            usage: { input_tokens: 1500, output_tokens: 200 },
        };
        const w = mount(ChatMessage, { props: { message } });

        expect(w.text()).toContain('1 poziva alata');
        expect(w.text()).toMatch(/1\.500 ulaznih/);
    });

    it('prikazuje grešku', () => {
        const w = mount(ChatMessage, { props: { message: { role: 'assistant', content: 'Greška agenta', error: true } } });

        expect(w.text()).toBe('Greška agenta');
        expect(w.find('div').classes()).toContain('bg-red-50');
    });
});
