import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type ApplicantType = 'organization' | 'individual';

export type AwardCategory = {
    id: number;
    name: string;
    description: string | null;
    applicant_type: ApplicantType;
    paper_template_url: string | null;
};

export type InitiativeData = {
    award_category_id: string;
    initiative_title: string;
    initiative_description: string;
};

export const INITIATIVE_FIELDS = [
    'award_category_id',
    'initiative_title',
    'initiative_description',
] as const satisfies readonly (keyof InitiativeData)[];

export const APPLICANT_TYPE_LABELS: Record<ApplicantType, string> = {
    organization: 'Organization',
    individual: 'Individual',
};

const DESCRIPTION_MAX_LENGTH = 1000;

type InitiativeFieldsProps = {
    categories: AwardCategory[];
    data: InitiativeData;
    errors: Partial<Record<keyof InitiativeData, string>>;
    setData: <K extends keyof InitiativeData>(
        field: K,
        value: InitiativeData[K],
    ) => void;
    registeredCategoryIds?: number[];
};

export function findCategory(
    categories: AwardCategory[],
    categoryId: string,
): AwardCategory | undefined {
    return categories.find((category) => String(category.id) === categoryId);
}

export function InitiativeFields({
    categories,
    data,
    errors,
    setData,
    registeredCategoryIds = [],
}: InitiativeFieldsProps) {
    const selectedCategory = findCategory(categories, data.award_category_id);

    return (
        <div className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="award_category_id">Award category</Label>
                <Select
                    value={data.award_category_id}
                    onValueChange={(value) =>
                        setData('award_category_id', value)
                    }
                >
                    <SelectTrigger
                        id="award_category_id"
                        aria-invalid={Boolean(errors.award_category_id)}
                        className="w-full"
                    >
                        <SelectValue placeholder="Choose a category" />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((category) => {
                            const isRegistered = registeredCategoryIds.includes(
                                category.id,
                            );

                            return (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                    disabled={isRegistered}
                                >
                                    {category.name}
                                    {isRegistered && (
                                        <span className="text-xs text-muted-foreground">
                                            Already registered
                                        </span>
                                    )}
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>
                {selectedCategory && (
                    <p className="text-xs text-muted-foreground">
                        {APPLICANT_TYPE_LABELS[selectedCategory.applicant_type]}{' '}
                        category
                        {selectedCategory.description &&
                            ` · ${selectedCategory.description}`}
                    </p>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="initiative_title">Initiative title</Label>
                <Input
                    id="initiative_title"
                    aria-invalid={Boolean(errors.initiative_title)}
                    value={data.initiative_title}
                    onChange={(e) =>
                        setData('initiative_title', e.target.value)
                    }
                    placeholder="The name of your initiative"
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="initiative_description">
                    Short description
                </Label>
                <Textarea
                    id="initiative_description"
                    aria-invalid={Boolean(errors.initiative_description)}
                    rows={5}
                    maxLength={DESCRIPTION_MAX_LENGTH}
                    value={data.initiative_description}
                    onChange={(e) =>
                        setData('initiative_description', e.target.value)
                    }
                    placeholder="Briefly describe what the initiative does and its impact"
                />
                <p className="text-right text-xs text-muted-foreground">
                    {data.initiative_description.length}/
                    {DESCRIPTION_MAX_LENGTH}
                </p>
            </div>

            {selectedCategory?.paper_template_url && (
                <Button variant="outline" asChild>
                    <a href={selectedCategory.paper_template_url} download>
                        <Download />
                        Download submission paper template
                    </a>
                </Button>
            )}
        </div>
    );
}
