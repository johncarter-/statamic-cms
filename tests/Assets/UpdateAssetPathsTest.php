<?php

namespace Tests\Assets;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades;
use Statamic\Support\Arr;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class UpdateAssetPathsTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'file']); // Doesn't work when they're arrays since the object is stored in memory.
        Cache::clear();

        config(['filesystems.disks.test' => [
            'driver' => 'local',
            'root' => __DIR__.'/tmp',
        ]]);

        $this->container = tap(Facades\AssetContainer::make()->handle('test_container')->disk('test'))->save();
        $this->assetHoff = tap(Facades\Asset::make()->container('test_container')->path('hoff.jpg'))->save();
        $this->assetNorris = tap(Facades\Asset::make()->container('test_container')->path('norris.jpg'))->save();

        Storage::fake('test');
    }

    public function tearDown(): void
    {
        app('files')->deleteDirectory(__DIR__.'/tmp');

        parent::tearDown();
    }

    /** @test */
    public function it_updates_single_assets_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setBlueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'avatar',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                        'max_files' => 1
                    ],
                ],
                [
                    'handle' => 'product',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                        'max_files' => 1
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'avatar' => 'hoff.jpg',
            'product' => 'surfboard.jpg',
        ]))->save();

        $this->assertEquals('hoff.jpg', $entry->get('avatar'));
        $this->assertEquals('surfboard.jpg', $entry->get('product'));

        $this->assetHoff->path('hoff-new.jpg')->save();

        $this->assertEquals('hoff-new.jpg', $entry->fresh()->get('avatar'));
        $this->assertEquals('surfboard.jpg', $entry->fresh()->get('product'));
    }

    /** @test */
    public function it_updates_multi_assets_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setBlueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'pics',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'pics' => ['hoff.jpg', 'norris.jpg'],
        ]))->save();

        $this->assertEquals(['hoff.jpg', 'norris.jpg'], $entry->get('pics'));

        $this->assetNorris->path('content/norris.jpg')->save();

        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], $entry->fresh()->get('pics'));
    }

    /** @test */
    public function it_doesnt_update_assets_from_another_container()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setBlueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'avatar',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                        'max_files' => 1
                    ],
                ],
                [
                    'handle' => 'wrong_avatar',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'wrong_container',
                        'max_files' => 1
                    ],
                ],
                [
                    'handle' => 'pics',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                    ],
                ],
                [
                    'handle' => 'wrong_pics',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'wrong_container',
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'avatar' => 'hoff.jpg',
            'wrong_avatar' => 'hoff.jpg',
            'pics' => ['hoff.jpg', 'norris.jpg'],
            'wrong_pics' => ['hoff.jpg', 'norris.jpg'],
        ]))->save();

        $this->assertEquals('hoff.jpg', $entry->get('avatar'));
        $this->assertEquals('hoff.jpg', $entry->get('wrong_avatar'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], $entry->get('pics'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], $entry->get('wrong_pics'));

        $this->assetHoff->path('hoff-new.jpg')->save();

        $this->assertEquals('hoff-new.jpg', $entry->fresh()->get('avatar'));
        $this->assertEquals('hoff.jpg', $entry->fresh()->get('wrong_avatar'));
        $this->assertEquals(['hoff-new.jpg', 'norris.jpg'], $entry->fresh()->get('pics'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], $entry->fresh()->get('wrong_pics'));
    }

    /** @test */
    public function it_updates_nested_asset_fields_within_replicator_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setblueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'reppy',
                    'field' => [
                        'type' => 'replicator',
                        'sets' => [
                            'set_one' => [
                                'fields' => [
                                    [
                                        'handle' => 'product',
                                        'field' => [
                                            'type' => 'assets',
                                            'container' => 'test_container',
                                            'max_files' => 1
                                        ],
                                    ],
                                    [
                                        'handle' => 'pics',
                                        'field' => [
                                            'type' => 'assets',
                                            'container' => 'test_container',
                                        ],
                                    ],
                                ],
                            ],
                            'set_two' => [
                                'fields' => [
                                    [
                                        'handle' => 'not_asset',
                                        'field' => [
                                            'type' => 'text',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'reppy' => [
                [
                    'type' => 'set_one',
                    'product' => 'norris.jpg',
                    'pics' => ['hoff.jpg', 'norris.jpg'],
                ],
                [
                    'type' => 'set_two',
                    'not_asset' => 'not an asset',
                ],
                [
                    'type' => 'set_one',
                    'product' => 'hoff.jpg',
                    'pics' => ['hoff.jpg', 'norris.jpg'],
                ],
            ],
        ]))->save();

        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'reppy.0.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'reppy.0.pics'));
        $this->assertEquals('not an asset', Arr::get($entry->data(), 'reppy.1.not_asset'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->data(), 'reppy.2.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'reppy.2.pics'));

        $this->assetNorris->path('content/norris.jpg')->save();

        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'reppy.0.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'reppy.0.pics'));
        $this->assertEquals('not an asset', Arr::get($entry->fresh()->data(), 'reppy.1.not_asset'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->fresh()->data(), 'reppy.2.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'reppy.2.pics'));
    }

    /** @test */
    public function it_updates_nested_asset_fields_within_grid_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setblueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'griddy',
                    'field' => [
                        'type' => 'grid',
                        'fields' => [
                            [
                                'handle' => 'product',
                                'field' => [
                                    'type' => 'assets',
                                    'container' => 'test_container',
                                    'max_files' => 1
                                ],
                            ],
                            [
                                'handle' => 'pics',
                                'field' => [
                                    'type' => 'assets',
                                    'container' => 'test_container',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'griddy' => [
                [
                    'product' => 'norris.jpg',
                    'pics' => ['hoff.jpg', 'norris.jpg'],
                ],
                [
                    'product' => 'hoff.jpg',
                    'pics' => ['hoff.jpg', 'norris.jpg'],
                ],
            ],
        ]))->save();

        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'griddy.0.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'griddy.0.pics'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->data(), 'griddy.1.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'griddy.1.pics'));

        $this->assetNorris->path('content/norris.jpg')->save();

        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'griddy.0.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'griddy.0.pics'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->fresh()->data(), 'griddy.1.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'griddy.1.pics'));
    }

    /** @test */
    public function it_updates_nested_asset_fields_within_bard_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setblueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'bardo',
                    'field' => [
                        'type' => 'bard',
                        'sets' => [
                            'set_one' => [
                                'fields' => [
                                    [
                                        'handle' => 'product',
                                        'field' => [
                                            'type' => 'assets',
                                            'container' => 'test_container',
                                            'max_files' => 1
                                        ],
                                    ],
                                    [
                                        'handle' => 'pics',
                                        'field' => [
                                            'type' => 'assets',
                                            'container' => 'test_container',
                                        ],
                                    ],
                                ],
                            ],
                            'set_two' => [
                                'fields' => [
                                    [
                                        'handle' => 'not_asset',
                                        'field' => [
                                            'type' => 'text',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'bardo' => [
                [
                    'type' => 'set',
                    'attrs' => [
                        'values' => [
                            'type' => 'set_one',
                            'product' => 'norris.jpg',
                            'pics' => ['hoff.jpg', 'norris.jpg'],
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'not_asset' => 'not an asset',
                ],
                [
                    'type' => 'set',
                    'attrs' => [
                        'values' => [
                            'type' => 'set_one',
                            'product' => 'hoff.jpg',
                            'pics' => ['hoff.jpg', 'norris.jpg'],
                        ],
                    ],
                ],
            ],
        ]))->save();

        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'bardo.0.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'bardo.0.attrs.values.pics'));
        $this->assertEquals('not an asset', Arr::get($entry->data(), 'bardo.1.not_asset'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->data(), 'bardo.2.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'bardo.2.attrs.values.pics'));

        $this->assetNorris->path('content/norris.jpg')->save();

        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'bardo.0.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'bardo.0.attrs.values.pics'));
        $this->assertEquals('not an asset', Arr::get($entry->fresh()->data(), 'bardo.1.not_asset'));
        $this->assertEquals('hoff.jpg', Arr::get($entry->fresh()->data(), 'bardo.2.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'bardo.2.attrs.values.pics'));
    }

    /** @test */
    public function it_recursively_updates_nested_asset_fields()
    {
        $collection = tap(Facades\Collection::make('articles'))->save();

        $this->setblueprint('collections/articles', [
            'fields' => [
                [
                    'handle' => 'avatar',
                    'field' => [
                        'type' => 'assets',
                        'container' => 'test_container',
                        'max_files' => 1
                    ],
                ],
                [
                    'handle' => 'reppy',
                    'field' => [
                        'type' => 'replicator',
                        'sets' => [
                            'set_one' => [
                                'fields' => [
                                    [
                                        'handle' => 'bard_within_reppy',
                                        'field' => [
                                            'type' => 'bard',
                                            'sets' => [
                                                'set_two' => [
                                                    'fields' => [
                                                        [
                                                            'handle' => 'product',
                                                            'field' => [
                                                                'type' => 'assets',
                                                                'container' => 'test_container',
                                                                'max_files' => 1
                                                            ],
                                                        ],
                                                        [
                                                            'handle' => 'pics',
                                                            'field' => [
                                                                'type' => 'assets',
                                                                'container' => 'test_container',
                                                            ],
                                                        ],
                                                        [
                                                            'handle' => 'griddy',
                                                            'field' => [
                                                                'type' => 'grid',
                                                                'fields' => [
                                                                    [
                                                                        'handle' => 'product',
                                                                        'field' => [
                                                                            'type' => 'assets',
                                                                            'container' => 'test_container',
                                                                            'max_files' => 1
                                                                        ],
                                                                    ],
                                                                    [
                                                                        'handle' => 'pics',
                                                                        'field' => [
                                                                            'type' => 'assets',
                                                                            'container' => 'test_container',
                                                                        ],
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $entry = tap(Facades\Entry::make()->collection($collection)->data([
            'avatar' => 'norris.jpg',
            'reppy' => [
                [
                    'type' => 'huh',
                    'not_asset' => 'not an asset',
                ],
                [
                    'type' => 'set_one',
                    'bard_within_reppy' => [
                        [
                            'type' => 'set',
                            'attrs' => [
                                'values' => [
                                    'type' => 'set_two',
                                    'product' => 'norris.jpg',
                                    'pics' => ['hoff.jpg', 'norris.jpg'],
                                    'griddy' => [
                                        [
                                            'product' => 'norris.jpg',
                                            'pics' => ['hoff.jpg', 'norris.jpg'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]))->save();

        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'avatar'));
        $this->assertEquals('not an asset', Arr::get($entry->data(), 'reppy.0.not_asset'));
        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'reppy.1.bard_within_reppy.0.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'reppy.1.bard_within_reppy.0.attrs.values.pics'));
        $this->assertEquals('norris.jpg', Arr::get($entry->data(), 'reppy.1.bard_within_reppy.0.attrs.values.griddy.0.product'));
        $this->assertEquals(['hoff.jpg', 'norris.jpg'], Arr::get($entry->data(), 'reppy.1.bard_within_reppy.0.attrs.values.griddy.0.pics'));

        $this->assetNorris->path('content/norris.jpg')->save();

        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'avatar'));
        $this->assertEquals('not an asset', Arr::get($entry->fresh()->data(), 'reppy.0.not_asset'));
        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'reppy.1.bard_within_reppy.0.attrs.values.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'reppy.1.bard_within_reppy.0.attrs.values.pics'));
        $this->assertEquals('content/norris.jpg', Arr::get($entry->fresh()->data(), 'reppy.1.bard_within_reppy.0.attrs.values.griddy.0.product'));
        $this->assertEquals(['hoff.jpg', 'content/norris.jpg'], Arr::get($entry->fresh()->data(), 'reppy.1.bard_within_reppy.0.attrs.values.griddy.0.pics'));
    }

    protected function setBlueprint($namespace, $blueprintContents)
    {
        $blueprint = tap(Facades\Blueprint::make()->setContents($blueprintContents))->save();

        Facades\Blueprint::shouldReceive('in')->with($namespace)->andReturn(collect([$blueprint]));
    }
}