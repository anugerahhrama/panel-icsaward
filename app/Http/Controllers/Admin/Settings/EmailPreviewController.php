<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\BuildEmailPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\PreviewEmailTemplateRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class EmailPreviewController extends Controller
{
    /**
     * Render an email template from the unsaved form values with sample data; nothing is saved or sent.
     */
    public function __invoke(PreviewEmailTemplateRequest $request, BuildEmailPreview $buildEmailPreview): JsonResponse
    {
        $mailable = $buildEmailPreview->handle($request->string('template')->value(), [
            'subject' => $request->string('subject')->value(),
            'body' => $request->string('body')->value(),
            'contact_email' => $request->validated('contact_email') ?? Setting::get('contact_email', ''),
        ]);

        return response()->json([
            'subject' => $mailable->envelope()->subject,
            'html' => $mailable->render(),
        ]);
    }
}
