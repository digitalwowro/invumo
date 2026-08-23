import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ComponentStateGallery } from '@/components/design-system/component-state-gallery';
import type { GalleryLabels } from '@/components/design-system/component-state-gallery';

const romanianLabels: GalleryLabels = {
    title: 'Componente Invumo',
    subtitle: 'Verificare comună în limba română',
    field: 'Denumire client',
    fieldDescription: 'Folosește diacritice românești: ă â î ș ț.',
    actions: {
        primary: 'Salvează',
        secondary: 'Anulează',
        ghost: 'Previzualizează',
        destructive: 'Șterge',
    },
    statuses: {
        paid: 'Plătit',
        accepted: 'Acceptat',
        completed: 'Finalizat',
        overdue: 'Restant',
        rejected: 'Respins',
        failed: 'Eșuat',
        partial: 'Parțial',
        expired: 'Expirat',
        paused: 'Întrerupt',
        issued: 'Emis',
        sent: 'Trimis',
        active: 'Activ',
        unpaid: 'Neplătit',
        draft: 'Ciornă',
        cancelled: 'Anulat',
        archived: 'Arhivat',
    },
};

describe('ComponentStateGallery', () => {
    it('renders the shared state matrix with Romanian diacritics', () => {
        render(<ComponentStateGallery labels={romanianLabels} />);

        expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
            'Componente Invumo',
        );
        expect(
            screen.getByText('Folosește diacritice românești: ă â î ș ț.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Parțial')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Salvează' })).toBeEnabled();
    });
});
