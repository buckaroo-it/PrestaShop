import { describe, expect, it, vi } from 'vitest';
import { readRoute, writeRoute, VIEWS } from '@/lib/route';

const setHash = (hash) => window.history.replaceState(null, '', `/admin?token=x${hash}`);

describe('route', () => {
    it('lists the four dashboard views', () => {
        expect(VIEWS).toEqual(['settings', 'payment_methods', 'verification_methods', 'order_payment_methods']);
    });

    describe('readRoute', () => {
        it('falls back to settings without a hash', () => {
            expect(readRoute()).toEqual({ view: 'settings', detail: null });
        });

        it.each(VIEWS)('reads the %s view', (view) => {
            setHash(`#${view}`);
            expect(readRoute()).toEqual({ view, detail: null });
        });

        it('reads the view and the decoded detail', () => {
            setHash('#payment_methods/my%20method');
            expect(readRoute()).toEqual({ view: 'payment_methods', detail: 'my method' });
        });

        it('falls back to settings for an unknown view but keeps the detail', () => {
            setHash('#nope/ideal');
            expect(readRoute()).toEqual({ view: 'settings', detail: 'ideal' });
        });

        it('treats an empty detail as none', () => {
            setHash('#verification_methods/');
            expect(readRoute()).toEqual({ view: 'verification_methods', detail: null });
        });
    });

    describe('writeRoute', () => {
        it('writes the view to the hash and keeps path and query', () => {
            setHash('');
            writeRoute('payment_methods');

            expect(window.location.hash).toBe('#payment_methods');
            expect(window.location.search).toBe('?token=x');
            expect(window.location.pathname).toBe('/admin');
        });

        it('encodes the detail', () => {
            writeRoute('payment_methods', 'a/b c');
            expect(window.location.hash).toBe('#payment_methods/a%2Fb%20c');
            expect(readRoute()).toEqual({ view: 'payment_methods', detail: 'a/b c' });
        });

        it('uses replaceState (no history entries) and keeps the history state', () => {
            window.history.replaceState({ keep: 1 }, '', '/admin');
            const replace = vi.spyOn(window.history, 'replaceState');
            const push = vi.spyOn(window.history, 'pushState');
            const length = window.history.length;

            writeRoute('order_payment_methods');

            expect(replace).toHaveBeenCalledWith({ keep: 1 }, '', '#order_payment_methods');
            expect(push).not.toHaveBeenCalled();
            expect(window.history.length).toBe(length);
        });

        it('does nothing when the hash is already right', () => {
            setHash('#settings');
            const replace = vi.spyOn(window.history, 'replaceState');

            writeRoute('settings');

            expect(replace).not.toHaveBeenCalled();
        });
    });
});
