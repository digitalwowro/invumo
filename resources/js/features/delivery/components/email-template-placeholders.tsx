import { FormSection } from '@/components/app/form-section';
import { MetaLabel, SecondaryText } from '@/components/app/typography';

type Props = {
    title: string;
    description: string;
    items: { token: string; label: string }[];
};

export function EmailTemplatePlaceholders({
    title,
    description,
    items,
}: Props) {
    return (
        <FormSection title={title} description={description}>
            <ul className="grid min-w-0 gap-3 sm:grid-cols-2">
                {items.map((item) => (
                    <li
                        key={item.token}
                        className="min-w-0 rounded-md border border-border bg-surface-inset p-3"
                    >
                        <MetaLabel>{item.token}</MetaLabel>
                        <SecondaryText>{item.label}</SecondaryText>
                    </li>
                ))}
            </ul>
        </FormSection>
    );
}
