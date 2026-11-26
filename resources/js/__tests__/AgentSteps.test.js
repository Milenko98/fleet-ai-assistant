import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import AgentSteps from '../components/AgentSteps.vue';

const steps = [
    { korak: 1, alat: 'pretrazi_dokumente', ulaz: { upit: 'stanka' }, izlaz: { nacin: 'fulltext', rezultati: [] }, greska: false, trajanje_ms: 12 },
    { korak: 1, alat: 'potrosnja_goriva', ulaz: { registracija: 'XX' }, izlaz: { greska: 'Vozilo ne postoji' }, greska: true, trajanje_ms: 3 },
];

describe('AgentSteps', () => {
    it('je zatvoren i prikazuje sažetak alata', () => {
        const w = mount(AgentSteps, { props: { steps } });

        expect(w.text()).toContain('Koraci agenta');
        expect(w.text()).toContain('2');
        expect(w.text()).toContain('Pretraga dokumenata (RAG) → Potrošnja goriva');
        expect(w.find('ol').exists()).toBe(false);
    });

    it('otvara korake i prikazuje ulaz i izlaz alata', async () => {
        const w = mount(AgentSteps, { props: { steps } });

        await w.find('button').trigger('click');
        expect(w.findAll('ol > li')).toHaveLength(2);
        expect(w.text()).toContain('greška');
        expect(w.text()).toContain('fulltext');

        await w.findAll('ol > li button')[1].trigger('click');
        expect(w.find('pre').text()).toContain('"registracija": "XX"');
        expect(w.findAll('pre')[1].text()).toContain('Vozilo ne postoji');
    });
});
