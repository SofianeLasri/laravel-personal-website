<?php

namespace App\Http\Controllers;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Experience;
use App\Models\Feature;
use App\Models\OptimizedPicture;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Video;
use Exception;
use Illuminate\Support\Facades\DB;
use Route;

class DebugController extends Controller
{
    public function index()
    {
        // Vérifier que nous ne sommes pas en production
        if (! in_array(config('app.env'), ['local', 'development'])) {
            abort(404);
        }

        // Collecter les statistiques des modèles
        $modelStats = [
            'Creations' => Creation::count(),
            'CreationDrafts' => CreationDraft::count(),
            'Technologies' => Technology::count(),
            'TechnologiesWithCreations' => Technology::whereHas('creations')->count(),
            'Experiences' => Experience::count(),
            'People' => Person::count(),
            'Pictures' => Picture::count(),
            'OptimizedPictures' => OptimizedPicture::count(),
            'Videos' => Video::count(),
            'TranslationKeys' => TranslationKey::count(),
            'Translations' => Translation::count(),
            'Users' => User::count(),
            'Features' => Feature::count(),
            'Screenshots' => Screenshot::count(),
            'Tags' => Tag::count(),
            'TechnologyExperiences' => TechnologyExperience::count(),
        ];

        // Types de créations avec comptage
        $creationTypesRaw = Creation::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $creationTypes = [];
        foreach ($creationTypesRaw as $item) {
            $creationTypes[$item->type->value] = $item->getAttribute('count');
        }

        // Types de technologies avec comptage
        $technologyTypesRaw = Technology::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $technologyTypes = [];
        foreach ($technologyTypesRaw as $item) {
            $technologyTypes[$item->type->value] = $item->getAttribute('count');
        }

        // Relations technologies-créations
        $techCreationRelations = DB::table('creation_technology')
            ->select('technology_id', DB::raw('count(*) as creation_count'))
            ->groupBy('technology_id')
            ->join('technologies', 'technologies.id', '=', 'creation_technology.technology_id')
            ->select('technologies.name', DB::raw('count(*) as creation_count'))
            ->groupBy('technologies.name')
            ->orderBy('creation_count', 'desc')
            ->limit(10)
            ->get();

        // Variables d'environnement (filtrées)
        $envVars = [
            'APP_ENV' => config('app.env'),
            'APP_DEBUG' => config('app.debug'),
            'APP_URL' => config('app.url'),
            'DB_CONNECTION' => config('database.default'),
            'CACHE_DRIVER' => config('cache.default'),
            'QUEUE_CONNECTION' => config('queue.default'),
            'SESSION_DRIVER' => config('session.driver'),
            'FILESYSTEM_DISK' => config('filesystems.default'),
            'BROADCAST_DRIVER' => config('broadcasting.default'),
            'LOG_CHANNEL' => config('logging.default'),
            'PHP_VERSION' => PHP_VERSION,
            'LARAVEL_VERSION' => app()->version(),
        ];

        // Informations sur la base de données
        $dbConnection = config('database.default');
        $tablesCount = 0;

        try {
            if ($dbConnection === 'sqlite') {
                $tablesCount = count(DB::select("SELECT name FROM sqlite_master WHERE type='table'"));
            } elseif ($dbConnection === 'mysql') {
                $tablesCount = count(DB::select('SHOW TABLES'));
            } elseif ($dbConnection === 'pgsql') {
                $tablesCount = count(DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'"));
            }
        } catch (Exception $e) {
            $tablesCount = 'Error: '.$e->getMessage();
        }

        $dbInfo = [
            'connection' => $dbConnection,
            'database' => config('database.connections.'.$dbConnection.'.database'),
            'driver' => config('database.connections.'.$dbConnection.'.driver'),
            'tables_count' => $tablesCount,
        ];

        // Dernières créations
        $latestCreations = Creation::latest()
            ->take(5)
            ->get(['id', 'name', 'slug', 'type', 'created_at']);

        // Technologies les plus utilisées
        $topTechnologies = Technology::withCount('creations')
            ->orderBy('creations_count', 'desc')
            ->take(10)
            ->get(['id', 'name', 'type', 'creations_count']);

        // Statistiques des traductions
        $translationStats = Translation::select('locale', DB::raw('count(*) as count'))
            ->groupBy('locale')
            ->get()
            ->pluck('count', 'locale')
            ->toArray();

        // Informations sur les fichiers et stockage
        $storageInfo = [
            'default_disk' => config('filesystems.default'),
            'public_disk_path' => storage_path('app/public'),
            'uploads_exist' => file_exists(storage_path('app/public/uploads')),
            'storage_link_exists' => file_exists(public_path('storage')),
        ];

        // Routes disponibles
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'methods' => implode('|', $route->methods()),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        })->filter(function ($route) {
            // Filtrer les routes internes Laravel
            return ! str_starts_with($route['uri'], '_') &&
                   ! str_starts_with($route['uri'], 'sanctum') &&
                   ! str_starts_with($route['uri'], 'livewire');
        })->take(30);

        return view('debug.index', compact(
            'modelStats',
            'creationTypes',
            'technologyTypes',
            'techCreationRelations',
            'envVars',
            'dbInfo',
            'latestCreations',
            'topTechnologies',
            'translationStats',
            'storageInfo',
            'routes'
        ));
    }
}
