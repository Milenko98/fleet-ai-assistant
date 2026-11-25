// Mali omotač oko fetch-a za pozive Laravel API-ja

async function request(method, url, body) {
    const res = await fetch(`/api${url}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (res.status === 204) return null;

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(firstError || data.message || `Greška ${res.status}`);
    }

    return data;
}

export const api = {
    chat: (messages) => request('POST', '/chat', { messages }),
    summary: () => request('GET', '/fleet/summary'),
    documents: () => request('GET', '/documents'),
    document: (id) => request('GET', `/documents/${id}`),
    addDocument: (naslov, sadrzaj) => request('POST', '/documents', { naslov, sadrzaj }),
    deleteDocument: (id) => request('DELETE', `/documents/${id}`),
    searchDocuments: (q) => request('GET', `/documents/search?q=${encodeURIComponent(q)}`),
};
