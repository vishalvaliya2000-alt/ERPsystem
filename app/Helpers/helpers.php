<?php

use Carbon\Carbon;

if (! function_exists('formatINR')) {
    function formatINR($amount): string
    {
        if ($amount === null || ! is_numeric($amount)) {
            return '₹0';
        }
        $amt = (float) $amount;

        return '₹'.number_format($amt, 0, '.', ',');
    }
}

if (! function_exists('formatCompactINR')) {
    function formatCompactINR($amount): string
    {
        if ($amount === null || ! is_numeric($amount)) {
            return '₹0';
        }
        $amt = (float) $amount;
        if (abs($amt) >= 10000000) {
            return '₹'.number_format($amt / 10000000, 2).' Cr';
        }
        if (abs($amt) >= 100000) {
            return '₹'.number_format($amt / 100000, 2).' L';
        }
        if (abs($amt) >= 1000) {
            return '₹'.number_format($amt / 1000, 1).' k';
        }

        return '₹'.number_format($amt, 0);
    }
}

if (! function_exists('formatQuantity')) {
    function formatQuantity($qty, string $uom = 'KG'): string
    {
        if ($qty === null || ! is_numeric($qty)) {
            return "0 {$uom}";
        }

        return number_format((float) $qty, 0)." {$uom}";
    }
}

if (! function_exists('formatWeightMT')) {
    function formatWeightMT($qty): string
    {
        if ($qty === null || ! is_numeric($qty)) {
            return '0 KG';
        }
        $val = (float) $qty;
        if ($val >= 1000) {
            $mt = round($val / 1000, 2);

            return number_format($mt, 2).' MT';
        }

        return number_format($val, 0).' KG';
    }
}

if (! function_exists('formatRelativeDate')) {
    function formatRelativeDate($date): array
    {
        if (! $date) {
            return ['text' => 'No date', 'isOverdue' => false, 'isToday' => false];
        }
        $target = $date instanceof Carbon ? $date : Carbon::parse($date);
        $now = Carbon::today();
        $targetDay = $target->copy()->startOfDay();

        $diffDays = $now->diffInDays($targetDay, false);

        if ($diffDays == 0) {
            return ['text' => 'Today', 'isOverdue' => false, 'isToday' => true];
        }
        if ($diffDays == 1) {
            return ['text' => 'Tomorrow', 'isOverdue' => false, 'isToday' => false];
        }
        if ($diffDays == -1) {
            return ['text' => 'Yesterday (Overdue)', 'isOverdue' => true, 'isToday' => false];
        }
        if ($diffDays < -1) {
            return ['text' => abs($diffDays).'d overdue', 'isOverdue' => true, 'isToday' => false];
        }

        return ['text' => "In {$diffDays} days", 'isOverdue' => false, 'isToday' => false];
    }
}

if (! function_exists('getHealthBadge')) {
    function getHealthBadge(string $health): array
    {
        return match (strtoupper($health)) {
            'HEALTHY' => ['label' => 'Healthy', 'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
            'NEEDS_ATTENTION' => ['label' => 'Needs Attention', 'bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
            'AT_RISK' => ['label' => 'At Risk', 'bg' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500'],
            default => ['label' => 'Dormant', 'bg' => 'bg-slate-50 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
        };
    }
}

if (! function_exists('getPriorityBadge')) {
    function getPriorityBadge(string $priority): array
    {
        return match (strtoupper($priority)) {
            'URGENT' => ['label' => 'Urgent', 'bg' => 'bg-red-100 text-red-800 border-red-200', 'color' => 'text-red-600'],
            'HIGH' => ['label' => 'High', 'bg' => 'bg-orange-100 text-orange-800 border-orange-200', 'color' => 'text-orange-600'],
            'MEDIUM' => ['label' => 'Medium', 'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'color' => 'text-blue-600'],
            default => ['label' => 'Low', 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'color' => 'text-slate-500'],
        };
    }
}
