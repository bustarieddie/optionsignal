<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Menu is shared via a view composer (not boot) so the authenticated user
     * is resolved and we can filter items by the `permission` key per request.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $verticalMenuData = json_decode(file_get_contents(base_path('resources/menu/verticalMenu.json')));
            $horizontalMenuData = json_decode(file_get_contents(base_path('resources/menu/horizontalMenu.json')));

            $verticalMenuData->menu = $this->filterByPermission($verticalMenuData->menu ?? []);
            if (isset($horizontalMenuData->menu)) {
                $horizontalMenuData->menu = $this->filterByPermission($horizontalMenuData->menu);
            }

            $view->with('menuData', [$verticalMenuData, $horizontalMenuData]);
        });
    }

    /**
     * Recursively drop menu items the current user lacks permission for.
     * Items without a `permission` key are always visible.
     *
     * @param  array<int, object>  $items
     * @return array<int, object>
     */
    protected function filterByPermission(array $items): array
    {
        $user = Auth::user();

        return array_values(array_filter($items, function ($item) use ($user) {
            if (isset($item->permission)) {
                if (! $user || ! $user->can($item->permission)) {
                    return false;
                }
            }

            if (isset($item->submenu)) {
                $item->submenu = $this->filterByPermission($item->submenu);
            }

            return true;
        }));
    }
}
