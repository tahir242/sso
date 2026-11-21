<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\SettingsPage;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use UnitEnum;

/**
 * Common behaviour for settings screens: super-admin only, grouped under
 * "Settings", and secret fields are never sent back to the browser — leaving
 * one empty keeps the stored value.
 */
abstract class BaseSettingsPage extends SettingsPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    /** @var array<int, string> */
    protected array $secretFields = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->secretFields as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $current = app(static::getSettings());

        foreach ($this->secretFields as $field) {
            if (blank($data[$field] ?? null)) {
                $data[$field] = $current->{$field};
            }
        }

        return self::emptyValuesForTypedProperties($data, static::getSettings());
    }

    /**
     * Filament sends an empty text or tags field as null, but a setting typed
     * `string` or `array` (not nullable) cannot hold null. Store '' / []
     * instead, so optional fields can be left empty.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string  $settings
     * @return array<string, mixed>
     */
    public static function emptyValuesForTypedProperties(array $data, string $settings): array
    {
        foreach ((new ReflectionClass($settings))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $type = $property->getType();

            if ($property->isStatic() || ! array_key_exists($name, $data) || $data[$name] !== null
                || ! $type instanceof ReflectionNamedType || $type->allowsNull()) {
                continue;
            }

            $data[$name] = match ($type->getName()) {
                'string' => '',
                'array' => [],
                default => $data[$name],
            };
        }

        return $data;
    }
}
