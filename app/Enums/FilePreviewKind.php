<?php

namespace App\Enums;

enum FilePreviewKind: string
{
    case Pdf = 'pdf';
    case Image = 'image';
    case Office = 'office';
    case Download = 'download';

    private const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const array OFFICE_EXTENSIONS = ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * Decide how the admin panel previews a file from its extension; anything unknown can only be downloaded.
     */
    public static function fromFileName(?string $name): self
    {
        $extension = strtolower(pathinfo($name ?? '', PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' => self::Pdf,
            in_array($extension, self::IMAGE_EXTENSIONS, true) => self::Image,
            in_array($extension, self::OFFICE_EXTENSIONS, true) => self::Office,
            default => self::Download,
        };
    }
}
