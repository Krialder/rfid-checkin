<?php

declare(strict_types=1);

namespace RfidCheckin\Models;

/**
 * Base Model Class
 * 
 * Provides common functionality for all model classes including
 * data validation, serialization, and attribute management.
 * 
 * @package RfidCheckin\Models
 * @version 1.0.0
 */
abstract class BaseModel
{
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    protected array $dates = [];
    protected bool $exists = false;

    /**
     * Create new model instance
     * 
     * @param array $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill model with attributes
     * 
     * @param array $attributes Attributes to fill
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Set attribute value
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return self
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get attribute value
     * 
     * @param string $key Attribute key
     * @param mixed $default Default value
     * @return mixed Attribute value
     */
    public function getAttribute(string $key, $default = null)
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $default;
        }

        $value = $this->attributes[$key];

        // Apply casting
        if (array_key_exists($key, $this->casts)) {
            $value = $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Check if attribute exists
     * 
     * @param string $key Attribute key
     * @return bool True if exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Check if key is fillable
     * 
     * @param string $key Attribute key
     * @return bool True if fillable
     */
    protected function isFillable(string $key): bool
    {
        return empty($this->fillable) || in_array($key, $this->fillable);
    }

    /**
     * Cast attribute to specified type
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return mixed Casted value
     */
    protected function castAttribute(string $key, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return is_string($value) ? json_decode($value, true) : (array) $value;
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'datetime':
                return is_string($value) ? new \DateTime($value) : $value;
            default:
                return $value;
        }
    }

    /**
     * Convert model to array
     * 
     * @param bool $includeHidden Include hidden attributes
     * @return array Model as array
     */
    public function toArray(bool $includeHidden = false): array
    {
        $attributes = $this->attributes;

        if (!$includeHidden) {
            foreach ($this->hidden as $hidden) {
                unset($attributes[$hidden]);
            }
        }

        // Apply casting to all attributes
        foreach ($attributes as $key => $value) {
            if (array_key_exists($key, $this->casts)) {
                $attributes[$key] = $this->castAttribute($key, $value);
            }
        }

        return $attributes;
    }

    /**
     * Convert model to JSON
     * 
     * @param bool $includeHidden Include hidden attributes
     * @return string JSON representation
     */
    public function toJson(bool $includeHidden = false): string
    {
        return json_encode($this->toArray($includeHidden));
    }

    /**
     * Mark model as existing in database
     * 
     * @param bool $exists Existence status
     * @return self
     */
    public function setExists(bool $exists): self
    {
        $this->exists = $exists;
        return $this;
    }

    /**
     * Check if model exists in database
     * 
     * @return bool True if exists
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Get all attributes
     * 
     * @return array All attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Magic getter for attributes
     * 
     * @param string $key Attribute key
     * @return mixed Attribute value
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes
     * 
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset check
     * 
     * @param string $key Attribute key
     * @return bool True if set
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Magic unset
     * 
     * @param string $key Attribute key
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Convert to string
     * 
     * @return string JSON representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
