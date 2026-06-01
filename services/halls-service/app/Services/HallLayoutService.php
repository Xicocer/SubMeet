<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class HallLayoutService
{
    private const TYPE_STAGE = 'stage';
    private const TYPE_SEAT = 'seat';
    private const TYPE_VIP_SEAT = 'vip_seat';
    private const TYPE_DANCEFLOOR = 'dancefloor';
    private const TYPE_TABLE = 'table';

    /**
     * @param array<string, mixed> $layout
     * @return array<string, int|bool>
     *
     * @throws ValidationException
     */
    public function validateAndSummarize(array $layout): array
    {
        $errors = [];

        $levels = $layout['levels'] ?? [];
        $elements = $layout['elements'] ?? [];

        if (!is_array($levels)) {
            $errors['layout.levels'][] = 'Levels must be an array.';
            $levels = [];
        }

        if (!is_array($elements) || $elements === []) {
            $errors['layout.elements'][] = 'Layout must contain at least one element.';
            $elements = [];
        }

        $levelIds = [];
        $elementIds = [];
        $stageCount = 0;
        $dancefloorCount = 0;
        $seatCapacity = 0;
        $tableCapacity = 0;
        $vipCapacity = 0;
        $dancefloorCapacity = 0;

        foreach ($levels as $index => $level) {
            $path = "layout.levels.{$index}";

            if (!is_array($level)) {
                $errors[$path][] = 'Each level must be an object.';
                continue;
            }

            $levelId = trim((string) ($level['id'] ?? ''));
            $levelName = trim((string) ($level['name'] ?? ''));

            if ($levelId === '') {
                $errors["{$path}.id"][] = 'Level id is required.';
                continue;
            }

            if (isset($levelIds[$levelId])) {
                $errors["{$path}.id"][] = 'Level id must be unique.';
            }

            if ($levelName === '') {
                $errors["{$path}.name"][] = 'Level name is required.';
            }

            $levelIds[$levelId] = true;
        }

        foreach ($elements as $index => $element) {
            $path = "layout.elements.{$index}";

            if (!is_array($element)) {
                $errors[$path][] = 'Each element must be an object.';
                continue;
            }

            $elementId = trim((string) ($element['id'] ?? ''));
            $type = (string) ($element['type'] ?? '');

            if ($elementId === '') {
                $errors["{$path}.id"][] = 'Element id is required.';
            } elseif (isset($elementIds[$elementId])) {
                $errors["{$path}.id"][] = 'Element id must be unique.';
            } else {
                $elementIds[$elementId] = true;
            }

            if (!in_array($type, [
                self::TYPE_STAGE,
                self::TYPE_SEAT,
                self::TYPE_VIP_SEAT,
                self::TYPE_DANCEFLOOR,
                self::TYPE_TABLE,
            ], true)) {
                $errors["{$path}.type"][] = 'Unsupported hall element type.';
                continue;
            }

            if (!$this->isNumeric($element['x'] ?? null)) {
                $errors["{$path}.x"][] = 'Element x coordinate is required.';
            }

            if (!$this->isNumeric($element['y'] ?? null)) {
                $errors["{$path}.y"][] = 'Element y coordinate is required.';
            }

            $levelId = trim((string) ($element['level_id'] ?? ''));

            if ($levelId !== '' && !isset($levelIds[$levelId])) {
                $errors["{$path}.level_id"][] = 'Referenced level does not exist.';
            }

            switch ($type) {
                case self::TYPE_STAGE:
                    $stageCount++;
                    $this->ensurePositiveSize($errors, $path, $element);
                    break;

                case self::TYPE_DANCEFLOOR:
                    $dancefloorCount++;
                    $this->ensurePositiveSize($errors, $path, $element);

                    $capacity = $element['capacity'] ?? null;

                    if (!is_int($capacity) && !ctype_digit((string) $capacity)) {
                        $errors["{$path}.capacity"][] = 'Dancefloor capacity must be a positive integer.';
                        break;
                    }

                    $capacity = (int) $capacity;

                    if ($capacity < 1) {
                        $errors["{$path}.capacity"][] = 'Dancefloor capacity must be at least 1.';
                        break;
                    }

                    $dancefloorCapacity += $capacity;
                    break;

                case self::TYPE_TABLE:
                    $this->ensurePositiveSize($errors, $path, $element);

                    $capacity = $this->resolvePositiveCapacity(
                        $errors,
                        "{$path}.capacity",
                        $element['capacity'] ?? 2,
                        'Table capacity must be a positive integer.',
                    );

                    if ($capacity !== null) {
                        $tableCapacity += $capacity;
                    }

                    break;

                case self::TYPE_SEAT:
                case self::TYPE_VIP_SEAT:
                    $label = trim((string) ($element['label'] ?? ''));
                    $row = trim((string) ($element['row'] ?? ''));
                    $number = trim((string) ($element['number'] ?? ''));

                    if ($label === '' && ($row === '' || $number === '')) {
                        $errors["{$path}.label"][] = 'Seat elements must have a label or a row and number.';
                    }

                    if ($type === self::TYPE_SEAT) {
                        $seatCapacity++;
                    } else {
                        $vipCapacity++;
                    }
                    break;
            }
        }

        if ($stageCount > 1) {
            $errors['layout.stage'][] = 'Only one stage is supported in the MVP hall editor.';
        }

        if ($dancefloorCount > 1) {
            $errors['layout.dancefloor'][] = 'Only one dancefloor is supported in the MVP hall editor.';
        }

        $totalCapacity = $seatCapacity + $tableCapacity + $vipCapacity + $dancefloorCapacity;

        if ($totalCapacity < 1) {
            $errors['layout.capacity'][] = 'Hall must contain at least one bookable place or dancefloor capacity.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'seat_capacity' => $seatCapacity + $tableCapacity,
            'table_capacity' => $tableCapacity,
            'vip_capacity' => $vipCapacity,
            'dancefloor_capacity' => $dancefloorCapacity,
            'total_capacity' => $totalCapacity,
            'levels_count' => count($levelIds),
            'elements_count' => count($elementIds),
            'has_dancefloor' => $dancefloorCount > 0,
        ];
    }

    /**
     * @param array<string, array<int, string>> $errors
     * @param array<string, mixed> $element
     */
    private function ensurePositiveSize(array &$errors, string $path, array $element): void
    {
        if (!$this->isPositiveNumber($element['width'] ?? null)) {
            $errors["{$path}.width"][] = 'Element width must be greater than 0.';
        }

        if (!$this->isPositiveNumber($element['height'] ?? null)) {
            $errors["{$path}.height"][] = 'Element height must be greater than 0.';
        }
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private function resolvePositiveCapacity(array &$errors, string $path, mixed $value, string $message): ?int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            $errors[$path][] = $message;

            return null;
        }

        $capacity = (int) $value;

        if ($capacity < 1) {
            $errors[$path][] = $message;

            return null;
        }

        return $capacity;
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    private function isPositiveNumber(mixed $value): bool
    {
        return $this->isNumeric($value) && (float) $value > 0;
    }
}
