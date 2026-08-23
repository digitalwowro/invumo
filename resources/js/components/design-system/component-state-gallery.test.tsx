import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ComponentStateGallery } from '@/components/design-system/component-state-gallery';
import {
    romanianDesignSystemTranslations,
    romanianStatusLabels,
} from '@/test/fixtures/design-system';

describe('ComponentStateGallery', () => {
    it('renders the complete shared state matrix with Romanian diacritics', () => {
        render(
            <ComponentStateGallery
                labels={romanianDesignSystemTranslations}
                statusLabels={romanianStatusLabels}
            />,
        );

        expect(
            screen.getAllByRole('heading', { level: 1 })[0],
        ).toHaveTextContent('Sistemul de componente Invumo');
        expect(
            screen.getByText('Text obișnuit cu diacritice: ă â î ș ț.'),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Parțial').length).toBeGreaterThan(0);
        expect(
            screen.getByRole('button', { name: 'Salvează modificările' }),
        ).toBeEnabled();
        expect(
            screen.getByRole('table', {
                name: 'Exemplu de listă cu facturi',
            }),
        ).toBeInTheDocument();
    });

    it('opens the shared confirmation dialog and exposes localized actions', async () => {
        const user = userEvent.setup();

        render(
            <ComponentStateGallery
                labels={romanianDesignSystemTranslations}
                statusLabels={romanianStatusLabels}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Deschide confirmarea' }),
        );

        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Șterge ciorna' }),
        ).toBeEnabled();
        expect(
            screen.getByRole('button', { name: 'Închide fereastra' }),
        ).toBeEnabled();
    });
});
