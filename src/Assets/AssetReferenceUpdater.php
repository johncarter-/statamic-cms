<?php

namespace Statamic\Assets;

use Statamic\Fields\Fields;
use Statamic\Support\Arr;

class AssetReferenceUpdater
{
    protected $item;
    protected $container;
    protected $oldPath;
    protected $newPath;
    protected $updated;

    /**
     * Instantiate asset reference updater.
     *
     * @param mixed $item
     */
    public function __construct($item)
    {
        $this->item = $item;
    }

    /**
     * Instantiate asset reference updater.
     *
     * @param mixed $item
     * @return static
     */
    public static function item($item)
    {
        return new static($item);
    }

    /**
     * Update asset references.
     *
     * @param string $container
     * @param string $originalPath
     * @param string $newPath
     */
    public function updateAssetReferences(string $container, string $originalPath, string $newPath)
    {
        $this->container = $container;
        $this->originalPath = $originalPath;
        $this->newPath = $newPath;

        $this->updateAssets();

        if ($this->updated) {
            $this->item->save();
        }
    }

    /**
     * Update assets fields.
     *
     * @param null|string $dottedPrefix
     * @param null|\Statamic\Fields\Fields $fields
     */
    protected function updateAssets($dottedPrefix = null, $fields = null)
    {
        $fields = $fields
            ? $fields->all()
            : $this->item->blueprint()->fields()->all();

        $this
            ->updateAssetsFieldValues($dottedPrefix, $fields)
            ->updateNestedFieldValues($dottedPrefix, $fields);
    }

    /**
     * Update assets field values.
     *
     * @param null|string $dottedPrefix
     * @param null|\Statamic\Fields\Fields $fields
     * @return $this
     */
    protected function updateAssetsFieldValues($dottedPrefix, $fields)
    {
        $fields
            ->filter(function ($field) {
                return $field->type() === 'assets'
                    && $field->get('container') === $this->container;
            })
            ->each(function ($field) use ($dottedPrefix) {
                $field->get('max_files') === 1
                    ? $this->updateStringValue($dottedPrefix, $field)
                    : $this->updateArrayValue($dottedPrefix, $field);
            });

        return $this;
    }

    /**
     * Update nested field values.
     *
     * @param null|string $dottedPrefix
     * @param \Illuminate\Support\Collection $fields
     * @return $this
     */
    protected function updateNestedFieldValues($dottedPrefix, $fields)
    {
        $fields
            ->filter(function ($field) {
                return in_array($field->type(), ['replicator', 'grid', 'bard']);
            })
            ->each(function ($field) use ($dottedPrefix) {
                $method = 'update'.ucfirst($field->type()).'Children';
                $dottedKey = $dottedPrefix.$field->handle();

                $this->{$method}($dottedKey, $field);
            });

        return $this;
    }

    /**
     * Update replicator field children.
     *
     * @param string $dottedKey
     * @param \Statamic\Fields\Field $field
     */
    protected function updateReplicatorChildren($dottedKey, $field)
    {
        $data = $this->item->data();

        $sets = Arr::get($data, $dottedKey);

        collect($sets)->each(function ($set, $setKey) use ($dottedKey, $field) {
            $dottedPrefix = "{$dottedKey}.{$setKey}.";
            $setHandle = Arr::get($set, 'type');
            $fields = Arr::get($field->config(), "sets.{$setHandle}.fields");

            if ($setHandle && $fields) {
                $this->updateAssets($dottedPrefix, new Fields($fields));
            }
        });
    }

    /**
     * Update grid field children.
     *
     * @param string $dottedKey
     * @param \Statamic\Fields\Field $field
     */
    protected function updateGridChildren($dottedKey, $field)
    {
        $data = $this->item->data();

        $sets = Arr::get($data, $dottedKey);

        collect($sets)->each(function ($set, $setKey) use ($dottedKey, $field) {
            $dottedPrefix = "{$dottedKey}.{$setKey}.";
            $fields = Arr::get($field->config(), 'fields');

            if ($fields) {
                $this->updateAssets($dottedPrefix, new Fields($fields));
            }
        });
    }

    /**
     * Update bard field children.
     *
     * @param string $dottedKey
     * @param \Statamic\Fields\Field $field
     */
    protected function updateBardChildren($dottedKey, $field)
    {
        $data = $this->item->data();

        $sets = Arr::get($data, $dottedKey);

        collect($sets)->each(function ($set, $setKey) use ($dottedKey, $field) {
            $dottedPrefix = "{$dottedKey}.{$setKey}.attrs.values.";
            $setHandle = Arr::get($set, 'attrs.values.type');
            $fields = Arr::get($field->config(), "sets.{$setHandle}.fields");

            if ($setHandle && $fields) {
                $this->updateAssets($dottedPrefix, new Fields($fields));
            }
        });
    }

    /**
     * Update string value on item.
     *
     * @param null|string $dottedPrefix
     * @param \Statamic\Fields\Field $field
     */
    protected function updateStringValue($dottedPrefix, $field)
    {
        $data = $this->item->data()->all();

        $dottedKey = $dottedPrefix.$field->handle();

        if (Arr::get($data, $dottedKey) !== $this->originalPath) {
            return;
        }

        Arr::set($data, $dottedKey, $this->newPath);

        $this->item->data($data);

        $this->updated = true;
    }

    /**
     * Update array value on item.
     *
     * @param null|string $dottedPrefix
     * @param \Statamic\Fields\Field $field
     */
    protected function updateArrayValue($dottedPrefix, $field)
    {
        $data = $this->item->data()->all();

        $dottedKey = $dottedPrefix.$field->handle();

        $fieldData = collect(Arr::dot(Arr::get($data, $dottedKey)));

        if (! $fieldData->contains($this->originalPath)) {
            return;
        }

        $fieldData->transform(function ($value) {
            return $value === $this->originalPath ? $this->newPath : $value;
        });

        Arr::set($data, $dottedKey, $fieldData->all());

        $this->item->data($data);

        $this->updated = true;
    }
}
