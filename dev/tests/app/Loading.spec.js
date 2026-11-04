import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Loading from '@/components/Loading.vue';

describe('Loading', () => {
    it('renders a spinning icon in the default colour', () => {
        const svg = mount(Loading).get('svg');

        expect(svg.classes()).toEqual(expect.arrayContaining(['animate-spin', 'text-fifthly']));
    });

    it('uses the given colour instead', () => {
        const svg = mount(Loading, { props: { color: 'text-white' } }).get('svg');

        expect(svg.classes()).toContain('text-white');
        expect(svg.classes()).not.toContain('text-fifthly');
    });
});
