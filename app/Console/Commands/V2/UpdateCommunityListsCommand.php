<?php

namespace App\Console\Commands\V2;

use App\Models\Community;
use App\Traits\HelperTrait;
use Illuminate\Console\Command;

class UpdateCommunityListsCommand extends Command
{
    use HelperTrait;

    protected $signature = 'app:update-community-lists-command';
    protected $description = 'Update Database Community Lists';

    public function handle()
    {
        $countCommunities = Community::count();
        
        if ($countCommunities === 0) {
            $this->getAllCommunities();
        }

        $communities = $this->getApiData('bridge.list_communities', [
            'sort' => 'new',
            'last' => '',
        ]);

        collect($communities)
            ->map(function ($community) {
                return [
                    'name' => $community['name'],
                    'title' => $community['title'],
                    'created_at' => $community['created_at'],
                    'updated_at' => now(),
                ];
            })
            ->each(function ($community) {
                Community::updateOrCreate([
                    'name' => $community['name'],
                ], [
                    'title' => $community['title'],
                    'created_at' => $community['created_at'],
                ]);
            });
    }

    public function getAllCommunities()
    {
        $lastCommunityName = '';

        while (true) {
            $communities = $this->getApiData('bridge.list_communities', [
                'last' => $lastCommunityName,
            ]);

            if (empty($communities)) {
                break;
            }

            $pluckCommunities = collect($communities)
                ->map(function ($community) {
                    return [
                        'name' => $community['name'],
                        'title' => $community['title'],
                        'created_at' => $community['created_at'],
                        'updated_at' => now(),
                    ];
                })
                ->each(function ($community) {
                    Community::updateOrCreate([
                        'name' => $community['name'],
                    ], [
                        'title' => $community['title'],
                        'created_at' => $community['created_at'],
                    ]);
                });

            // Update the lastCommunityName for the next request
            $lastCommunityName = $pluckCommunities->last()['name'];
        }

    }
}
