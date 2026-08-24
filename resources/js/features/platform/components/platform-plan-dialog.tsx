import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { SelectField } from '@/components/app/select-field';
import { SystemMessage } from '@/components/app/system-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type {
    PlanStatus,
    PlatformAccountRow,
    PlatformPlan,
    PlatformTranslations,
} from '@/types';

type PlanDialogProps = {
    account: PlatformAccountRow;
    plans: PlatformPlan[];
    translations: PlatformTranslations;
};

const statuses: PlanStatus[] = [
    'TRIALING',
    'ACTIVE',
    'PAST_DUE',
    'CANCELED',
    'EXPIRED',
];

function localInputValue(value: string | null): string {
    return value ? new Date(value).toISOString().slice(0, 16) : '';
}

export function PlatformPlanDialog({
    account,
    plans,
    translations,
}: PlanDialogProps) {
    const formId = useId();
    const copy = translations.plan_lifecycle;
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [planId, setPlanId] = useState(account.planId);
    const [status, setStatus] = useState<PlanStatus>(account.planStatus);
    const [startedAt, setStartedAt] = useState(
        localInputValue(account.planStartedAt),
    );
    const [trialEndsAt, setTrialEndsAt] = useState(
        localInputValue(account.trialEndsAt),
    );
    const [accessEndsAt, setAccessEndsAt] = useState(
        localInputValue(account.accessEndsAt),
    );
    const [endedAt, setEndedAt] = useState(localInputValue(account.endedAt));
    const [cancelAtEnd, setCancelAtEnd] = useState(account.cancelAtPeriodEnd);
    const [reason, setReason] = useState('');
    const [confirmed, setConfirmed] = useState(false);

    const reset = () => {
        setPlanId(account.planId);
        setStatus(account.planStatus);
        setStartedAt(localInputValue(account.planStartedAt));
        setTrialEndsAt(localInputValue(account.trialEndsAt));
        setAccessEndsAt(localInputValue(account.accessEndsAt));
        setEndedAt(localInputValue(account.endedAt));
        setCancelAtEnd(account.cancelAtPeriodEnd);
        setReason('');
        setConfirmed(false);
        setErrors({});
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.patch(
            account.planUrl,
            {
                plan_id: planId,
                plan_status: status,
                plan_started_at: startedAt,
                trial_ends_at: trialEndsAt || null,
                access_ends_at: accessEndsAt || null,
                cancel_at_period_end: cancelAtEnd,
                ended_at: endedAt || null,
                reason,
                confirmed,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (nextErrors) => setErrors(nextErrors),
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            },
        );
    };

    const operationError = errors.operation ?? errors.confirmed;

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) {
                    reset();
                }

                setOpen(nextOpen);
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="secondary">
                    {copy.update}
                </Button>
            </DialogTrigger>
            <DialogContent
                className="sm:max-w-2xl"
                closeLabel={translations.common.close}
            >
                <DialogHeader>
                    <DialogTitle>{copy.update_title}</DialogTitle>
                    <DialogDescription>
                        {copy.update_description}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id={formId}
                    className="grid min-h-0 gap-5 overflow-y-auto sm:grid-cols-2"
                    onSubmit={submit}
                >
                    {operationError && (
                        <div className="sm:col-span-2">
                            <SystemMessage
                                title={operationError}
                                tone="error"
                            />
                        </div>
                    )}
                    <SelectField
                        name="plan_id"
                        label={copy.plan}
                        value={planId}
                        onValueChange={setPlanId}
                        error={errors.plan_id}
                        options={plans.map((plan) => ({
                            value: plan.id,
                            label: plan.name,
                        }))}
                        required
                    />
                    <SelectField
                        name="plan_status"
                        label={copy.status}
                        value={status}
                        onValueChange={(value) =>
                            setStatus(value as PlanStatus)
                        }
                        error={errors.plan_status}
                        options={statuses.map((value) => ({
                            value,
                            label: translations.statuses[value],
                        }))}
                        required
                    />
                    <DateField
                        label={copy.started_at}
                        value={startedAt}
                        setValue={setStartedAt}
                        error={errors.plan_started_at}
                        required
                    />
                    <DateField
                        label={copy.trial_ends_at}
                        value={trialEndsAt}
                        setValue={setTrialEndsAt}
                        error={errors.trial_ends_at}
                    />
                    <DateField
                        label={copy.access_ends_at}
                        value={accessEndsAt}
                        setValue={setAccessEndsAt}
                        error={errors.access_ends_at}
                    />
                    <DateField
                        label={copy.ended_at}
                        value={endedAt}
                        setValue={setEndedAt}
                        error={errors.ended_at}
                    />
                    <CheckboxField
                        label={copy.cancel_at_period_end}
                        checkbox={{
                            checked: cancelAtEnd,
                            onCheckedChange: (checked) =>
                                setCancelAtEnd(checked === true),
                        }}
                    />
                    <div className="sm:col-span-2">
                        <TextField
                            label={translations.common.reason}
                            error={errors.reason}
                            input={{
                                value: reason,
                                required: true,
                                maxLength: 500,
                                placeholder:
                                    translations.common.reason_placeholder,
                                onChange: (event) =>
                                    setReason(event.target.value),
                            }}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <CheckboxField
                            label={translations.common.confirm}
                            checkbox={{
                                checked: confirmed,
                                required: true,
                                onCheckedChange: (checked) =>
                                    setConfirmed(checked === true),
                            }}
                        />
                    </div>
                </form>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {translations.common.cancel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={processing || !confirmed}
                    >
                        {processing && <Spinner />}
                        {copy.update_confirm}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DateField({
    label,
    value,
    setValue,
    error,
    required,
}: {
    label: string;
    value: string;
    setValue: (value: string) => void;
    error?: string;
    required?: boolean;
}) {
    return (
        <TextField
            label={label}
            error={error}
            input={{
                type: 'datetime-local',
                value,
                required,
                onChange: (event) => setValue(event.target.value),
            }}
        />
    );
}
