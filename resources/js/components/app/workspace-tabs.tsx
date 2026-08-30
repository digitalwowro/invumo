import { Badge } from '@/components/ui/badge';
import { TabsList, TabsTrigger } from '@/components/ui/tabs';

export type WorkspaceTabItem<T extends string> = {
    value: T;
    label: string;
    pill: string;
};

type Props<T extends string> = {
    label: string;
    items: WorkspaceTabItem<T>[];
    testIdPrefix?: string;
};

export function WorkspaceTabs<T extends string>({
    label,
    items,
    testIdPrefix,
}: Props<T>) {
    return (
        <div className="min-w-0 overflow-x-auto">
            <TabsList
                variant="line"
                aria-label={label}
                className="h-auto min-h-0 min-w-max justify-start gap-0 p-0"
            >
                {items.map((item) => (
                    <TabsTrigger
                        key={item.value}
                        value={item.value}
                        data-testid={
                            testIdPrefix
                                ? `${testIdPrefix}-${item.value}-tab`
                                : undefined
                        }
                        className="group/tab flex-none rounded-none border-0 border-b-2 border-transparent px-3 py-2.5 after:hidden hover:bg-transparent data-[state=active]:border-b-foreground data-[state=active]:bg-transparent"
                    >
                        {item.label}
                        <Badge
                            variant="quiet"
                            className="border-transparent bg-surface-subtle px-1.5 py-0 text-[10px] tracking-normal normal-case group-data-[state=active]/tab:bg-surface-inset"
                        >
                            {item.pill}
                        </Badge>
                    </TabsTrigger>
                ))}
            </TabsList>
        </div>
    );
}
