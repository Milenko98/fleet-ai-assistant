import { describe, expect, it, vi } from 'vitest';
import { api } from '../api';

function mockFetch(status, body) {
    return vi.spyOn(globalThis, 'fetch').mockResolvedValue({
        ok: status < 400,
        status,
        json: async () => body,
    });
}

describe('api', () => {
    it('šalje razgovor na /api/chat kao JSON', async () => {
        const fetch = mockFetch(200, { answer: 'ok' });

        await api.chat([{ role: 'user', content: 'Bok' }]);

        const [url, opts] = fetch.mock.calls[0];
        expect(url).toBe('/api/chat');
        expect(opts.method).toBe('POST');
        expect(JSON.parse(opts.body)).toEqual({ messages: [{ role: 'user', content: 'Bok' }] });
    });

    it('vraća prvu validacijsku grešku', async () => {
        mockFetch(422, { message: 'The given data was invalid.', errors: { naslov: ['Naslov je obavezan.'] } });

        await expect(api.addDocument('', '')).rejects.toThrow('Naslov je obavezan.');
    });

    it('vraća poruku servera kod greške', async () => {
        mockFetch(500, { message: 'Greška agenta: nema ključa' });

        await expect(api.chat([])).rejects.toThrow('Greška agenta: nema ključa');
    });

    it('kodira upit za pretragu', async () => {
        const fetch = mockFetch(200, { rezultati: [] });

        await api.searchDocuments('stanka & odmor');

        expect(fetch.mock.calls[0][0]).toBe('/api/documents/search?q=stanka%20%26%20odmor');
    });
});
