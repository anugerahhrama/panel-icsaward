<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * Competition stages shown in the participant "What's next" panel. The date, title and description of each stage are
 * display text the committee edits in Settings → Registration & Deadlines; an empty title or description falls back
 * to the default copy below.
 */
enum TimelineStage: string
{
    case AdministrativeSelection = 'administrative_selection';
    case DeskEvaluation = 'desk_evaluation';
    case FinalistsAnnouncement = 'finalists_announcement';
    case Pitching = 'pitching';
    case AwardingNight = 'awarding_night';

    /**
     * The setting key holding the stage's free-text date.
     */
    public function dateKey(): string
    {
        return 'timeline_'.$this->value;
    }

    /**
     * The setting key holding the stage's custom title.
     */
    public function titleKey(): string
    {
        return $this->dateKey().'_title';
    }

    /**
     * The setting key holding the stage's custom description.
     */
    public function descriptionKey(): string
    {
        return $this->dateKey().'_description';
    }

    public function defaultTitle(): string
    {
        return match ($this) {
            self::AdministrativeSelection => 'Administrative selection',
            self::DeskEvaluation => 'Desk evaluation',
            self::FinalistsAnnouncement => 'Top 5 finalists announced',
            self::Pitching => 'Pitching session',
            self::AwardingNight => 'Awarding Night',
        };
    }

    public function defaultDescription(): string
    {
        return match ($this) {
            self::AdministrativeSelection => 'The committee checks your documents for completeness. Your status becomes Qualified or Needs Revision.',
            self::DeskEvaluation => 'Qualified submissions are scored by the Board of Judges in your category.',
            self::FinalistsAnnouncement => 'We will contact finalists by email and on this dashboard.',
            self::Pitching => 'Finalists present their initiative to the judges.',
            self::AwardingNight => 'Winners are announced at the Awarding Night.',
        };
    }

    /**
     * Every setting key the committee can edit for the timeline.
     *
     * @return list<string>
     */
    public static function settingKeys(): array
    {
        return array_merge(...array_map(
            fn (self $stage): array => [$stage->dateKey(), $stage->titleKey(), $stage->descriptionKey()],
            self::cases(),
        ));
    }

    /**
     * The stages as participants see them, with the committee's text or the default copy.
     *
     * @return list<array{title: string, description: string, date: string|null}>
     */
    public static function forParticipants(): array
    {
        $settings = Setting::many(self::settingKeys());

        return array_map(fn (self $stage): array => [
            'title' => $settings[$stage->titleKey()] ?: $stage->defaultTitle(),
            'description' => $settings[$stage->descriptionKey()] ?: $stage->defaultDescription(),
            'date' => $settings[$stage->dateKey()] ?: null,
        ], self::cases());
    }

    /**
     * The stages and their setting keys and default copy, for the admin settings form.
     *
     * @return list<array{dateKey: string, titleKey: string, descriptionKey: string, defaultTitle: string, defaultDescription: string}>
     */
    public static function forSettingsForm(): array
    {
        return array_map(fn (self $stage): array => [
            'dateKey' => $stage->dateKey(),
            'titleKey' => $stage->titleKey(),
            'descriptionKey' => $stage->descriptionKey(),
            'defaultTitle' => $stage->defaultTitle(),
            'defaultDescription' => $stage->defaultDescription(),
        ], self::cases());
    }
}
