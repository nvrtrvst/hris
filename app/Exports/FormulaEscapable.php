<?php

namespace App\Exports;

trait FormulaEscapable
{
    /**
     * Escape cell value to prevent Excel/CSV formula injection.
     * Cells starting with =, +, -, @, tab, or CR get a leading single-quote.
     */
    protected static function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $prefixes = ['=', '+', '-', '@', "\t", "\r"];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'{$value}";
            }
        }

        return $value;
    }
}
