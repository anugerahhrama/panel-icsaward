import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import JudgingSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/JudgingSettingsController';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/judging';

type Props = {
    settings: {
        judging_stage: string;
        normalization_enabled: boolean;
        normalization_min_sample: number;
        stage_1_weight: number;
        stage_2_weight: number;
    };
    stages: { value: string; label: string }[];
    weightsLocked: boolean;
};

const STAGE_DESCRIPTIONS: Record<string, string> = {
    closed: 'Judges can read their scores but cannot enter or change them.',
    desk_evaluation:
        'Judges score the qualified submissions in their assigned categories. Submitted scores stay editable while this stage is active. Categories with confirmed finalists stay read-only.',
    pitching:
        'Judges score the confirmed finalists in their assigned categories with the same rubric. Submitted scores stay editable while this stage is active.',
};

export default function JudgingSettings({
    settings,
    stages,
    weightsLocked,
}: Props) {
    const [stageOneWeight, setStageOneWeight] = useState(
        String(settings.stage_1_weight),
    );
    const [stageTwoWeight, setStageTwoWeight] = useState(
        String(settings.stage_2_weight),
    );
    const weightTotal = Number(stageOneWeight) + Number(stageTwoWeight);

    return (
        <>
            <Head title="Judging" />
            <Form
                {...JudgingSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Heading
                            title="Judging"
                            description="Open or close the judging stage, set how judge scores are normalized and how the final score weighs both stages."
                        />

                        <SettingsSection
                            title="Judging stage"
                            description="Checked on the server every time a judge saves scores."
                            defaultOpen
                        >
                            <div className="grid max-w-md gap-2">
                                <Label htmlFor="judging_stage">
                                    Active stage
                                </Label>
                                <Select
                                    name="judging_stage"
                                    defaultValue={settings.judging_stage}
                                >
                                    <SelectTrigger id="judging_stage">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {stages.map((stage) => (
                                            <SelectItem
                                                key={stage.value}
                                                value={stage.value}
                                            >
                                                {stage.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.judging_stage} />
                            </div>

                            <dl className="grid gap-3 text-sm">
                                {stages.map((stage) => (
                                    <div key={stage.value}>
                                        <dt className="font-medium">
                                            {stage.label}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {STAGE_DESCRIPTIONS[stage.value]}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </SettingsSection>

                        <SettingsSection
                            title="Normalization"
                            description="Used by Score Recap when Stage 1 or Stage 2 scores are recalculated."
                            defaultOpen
                        >
                            <div className="grid gap-1">
                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        name="normalization_enabled"
                                        value="1"
                                        defaultChecked={
                                            settings.normalization_enabled
                                        }
                                    />
                                    Normalize judge scores
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Each judge's weighted score is converted to
                                    a z-score against everything that judge
                                    scored in the stage, then scaled back to the
                                    overall 0–100 range. Turn off to rank by the
                                    average weighted raw score.
                                </p>
                                <InputError
                                    message={errors.normalization_enabled}
                                />
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="normalization_min_sample">
                                    Minimum sample per judge
                                </Label>
                                <Input
                                    id="normalization_min_sample"
                                    name="normalization_min_sample"
                                    type="number"
                                    min={2}
                                    max={50}
                                    defaultValue={
                                        settings.normalization_min_sample
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Judges who submitted fewer scores than this
                                    keep their weighted raw score.
                                </p>
                                <InputError
                                    message={errors.normalization_min_sample}
                                />
                            </div>
                        </SettingsSection>

                        <SettingsSection
                            title="Final score weights"
                            description="Final score = Stage 1 × Stage 1 weight + Stage 2 × Stage 2 weight. Applied when Stage 2 is recalculated in Score Recap."
                            defaultOpen
                        >
                            <div className="grid max-w-md grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="stage_1_weight">
                                        Stage 1 weight (%)
                                    </Label>
                                    <Input
                                        id="stage_1_weight"
                                        name="stage_1_weight"
                                        type="number"
                                        min={0}
                                        max={100}
                                        readOnly={weightsLocked}
                                        value={stageOneWeight}
                                        onChange={(event) =>
                                            setStageOneWeight(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="stage_2_weight">
                                        Stage 2 weight (%)
                                    </Label>
                                    <Input
                                        id="stage_2_weight"
                                        name="stage_2_weight"
                                        type="number"
                                        min={0}
                                        max={100}
                                        readOnly={weightsLocked}
                                        value={stageTwoWeight}
                                        onChange={(event) =>
                                            setStageTwoWeight(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <p
                                className={
                                    weightTotal === 100
                                        ? 'text-xs text-muted-foreground'
                                        : 'text-xs text-destructive'
                                }
                            >
                                {weightsLocked
                                    ? 'Awards are confirmed, so the weights can no longer change.'
                                    : `Total ${weightTotal}%. The weights must add up to 100%.`}
                            </p>
                            <InputError
                                message={
                                    errors.stage_1_weight ??
                                    errors.stage_2_weight
                                }
                            />
                        </SettingsSection>

                        <SaveButton
                            processing={processing}
                            disabled={weightTotal !== 100}
                        />
                    </>
                )}
            </Form>
        </>
    );
}

JudgingSettings.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Judging', href: edit() },
    ],
};
