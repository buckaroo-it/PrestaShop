import { describe, expect, it } from 'vitest';
import toastrLib from 'toastr';
import { useToastr } from '@/lib/toastr';

describe('useToastr', () => {
    it('returns the toastr library configured for the admin', () => {
        const { toastr } = useToastr();

        expect(toastr).toBe(toastrLib);
        expect(toastr.options).toMatchObject({
            positionClass: 'toast-bottom-right',
            preventDuplicates: true,
            newestOnTop: true,
            closeButton: false,
            timeOut: '3500',
        });
        expect(typeof toastr.success).toBe('function');
        expect(typeof toastr.error).toBe('function');
    });
});
