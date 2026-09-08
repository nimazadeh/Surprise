<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Http\RedirectResponse;

/**
 * Shared slug-lookup order for nested API endpoints (Phase 2.5): exact
 * slug → 301 via slug_redirects (C-01) → enveloped 404. Mirrors the public
 * web pages so renamed subjects behave identically on both surfaces.
 */
trait ResolvesSlugs
{
    protected function redirectOr404(string $slug, string $routeName, string $subject): RedirectResponse
    {
        $redirect = SlugRedirect::query()
            ->where('subject_type', $subject)
            ->where('old_slug', $slug)
            ->first();

        if ($redirect) {
            return redirect()->route('api.v1.'.$routeName, $redirect->new_slug, 301);
        }

        abort(404);
    }

    /**
     * Cap for nested list endpoints (plain arrays in v1).
     */
    protected function nestedMax(): int
    {
        return max(1, (int) config('shirin.catalogue.nested_max', 100));
    }
}
