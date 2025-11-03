<?php

namespace App\Services\Utils;

use Illuminate\Support\Facades\DB;

class VocabulaireService
{
    /**
     * Retourne deux maps:
     *  - labels['TRAITEMENT0'] = 'libellé'
     *  - codes['TRAITEMENT'][0] = 'CODE_A'
     */
    public function load(): array
    {
        $labels = [];
        $codes  = [];
        $rows = DB::table('t_actions_vocabulaire')->orderBy('pos_index')->get();
        foreach ($rows as $item) {
            $labels[$item->group_code . (int)$item->pos_index] = $item->label;
            $codes[$item->group_code][(int)$item->pos_index]   = $item->code;
        }
        return compact('labels', 'codes');
    }

    public function bitsFromPosted(array $posted, string $group, array $vocab): string
    {
        $map = $vocab['codes'][$group] ?? [];
        if (empty($map)) return '';
        $max  = (int) max(array_keys($map));
        $bits = '';
        for ($i = 0; $i <= $max; $i++) {
            $code = $map[$i] ?? null;
            $bits .= ($code && isset($posted[$code]) && (string)$posted[$code] === '1') ? '1' : '0';
        }
        return $bits;
    }

    public function labelsFromBits(string $group, string $bits, array $vocab): array
    {
        $out = [];
        $len = strlen($bits);
        for ($i = 0; $i < $len; $i++) {
            if ($bits[$i] === '1') {
                $key = $group . $i;
                if (isset($vocab['labels'][$key])) $out[] = $vocab['labels'][$key];
            }
        }
        return $out;
    }

    public function textFromBits(string $group, string $bits, array $vocab): string
    {
        return implode(', ', $this->labelsFromBits($group, $bits, $vocab));
    }

    public function pruneNulls(array $arr): array
    {
        return array_filter($arr, static fn($v) => $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
    }
}
