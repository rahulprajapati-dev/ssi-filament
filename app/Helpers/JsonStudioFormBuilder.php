<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Studio-specific form builder.
 *
 * Extends JsonFormBuilder for use inside Filament RelationManagers (Fields, Layouts).
 * Add overrides here when Studio forms need different behaviour from the generic builder.
 */
class JsonStudioFormBuilder extends JsonFormBuilder
{
}
