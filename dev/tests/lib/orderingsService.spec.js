import { describe, expect, it, vi } from 'vitest';
import { callsTo, fail, respond } from '../mocks/api';
import { useOrderingsService } from '@/lib/orderingsService';

vi.mock('@/lib/api', () => import('../mocks/api'));

describe('useOrderingsService', () => {
    it('getOrdering loads the orderings of a country', async () => {
        const orderings = { id: 1, country_id: 'NL', value: ['ideal', 'paypal'] };
        respond('buckaroo_config_orderings', { status: true, orderings }, 'get');
        const service = useOrderingsService();

        await service.getOrdering('NL');

        expect(callsTo('buckaroo_config_orderings', 'get')[0].query).toEqual({ country: 'NL' });
        expect(service.paymentOrderings.value).toEqual(orderings);
        expect(service.data.value.status).toBe(true);
    });

    it('getOrdering leaves the orderings alone when the status is false', async () => {
        respond('buckaroo_config_orderings', { status: false, orderings: { value: [] } }, 'get');
        const service = useOrderingsService();

        await service.getOrdering('BE');

        expect(service.paymentOrderings.value).toBeNull();
    });

    it('getOrdering rejects when the request fails', async () => {
        fail('buckaroo_config_orderings', new Error('boom'), 'get');
        const service = useOrderingsService();

        await expect(service.getOrdering('NL')).rejects.toThrow('boom');
        expect(service.paymentOrderings.value).toBeNull();
    });

    it('updateOrderings posts the orderings and returns the response', async () => {
        respond('buckaroo_config_orderings', { status: true }, 'post');
        const service = useOrderingsService();
        const payload = { country: 'NL', value: ['paypal', 'ideal'] };

        await expect(service.updateOrderings(payload)).resolves.toEqual({ status: true });
        expect(callsTo('buckaroo_config_orderings', 'post')[0].payload).toEqual(payload);
    });
});
