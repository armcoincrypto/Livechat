<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use App\Models\Banned;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlockIpController extends Controller
{
    /**
     * Фильтр по IP, Email, Domain, CIDR
     *
     * Query params:
     *  - per_page: int (1..200)
     *  - q: string (search)
     *  - status: active|expired
     *  - type: ip|email|domain|cidr
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 50);
        if ($perPage < 1) $perPage = 50;
        if ($perPage > 200) $perPage = 200;

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $type = (string) $request->input('type', '');

        $query = Banned::query()->orderByDesc('id');

        if ($q !== '') {
            $qLower = Str::lower($q);
            $query->where(function ($w) use ($q, $qLower) {
                $w->where('filter_name', 'like', '%' . $q . '%')
                    ->orWhere('filter_key', 'like', '%' . $qLower . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        if ($type !== '') {
            $type = Str::lower($type);
            if (in_array($type, ['ip', 'email', 'domain', 'cidr'], true)) {
                $query->where('type', $type);
            }
        }

        if ($status !== '') {
            $status = Str::lower($status);
            if ($status === 'active') {
                $query->where('expired_at', '>=', now());
            } elseif ($status === 'expired') {
                $query->where('expired_at', '<', now());
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (Banned $item) {
            return [
                'id' => (int) $item->id,
                'attributes' => [
                    'type' => $item->type,
                    'filter_key' => $item->filter_key,
                    'filter_name' => $item->filter_name,
                    'description' => $item->description,
                    'expired_at' => $item->expired_at?->toIso8601String(),
                ],
            ];
        })->values();

        return response()->json([
            'status' => 0,
            'data' => $data,
            'meta' => [
                'total' => (int) $paginator->total(),
                'per_page' => (int) $paginator->perPage(),
                'current_page' => (int) $paginator->currentPage(),
                'last_page' => (int) $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Detect type and normalize user input.
     *
     * Returns: [type, filter_key, filter_name, ip_from, ip_to]
     *
     * type: ip|cidr|email|domain|unknown
     * filter_key is the normalized key used for unique matching.
     */
    private function normalizeFilter(string $raw): array
    {
        $raw = trim($raw);
        $lower = Str::lower($raw);

        // CIDR (IPv4)
        if (preg_match('/^([0-9]{1,3}(?:\\.[0-9]{1,3}){3})\\/(\\d{1,2})$/', $lower, $m) === 1) {
            $ip = $m[1];
            $mask = (int) $m[2];
            if ($mask >= 0 && $mask <= 32 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                [$from, $to] = $this->cidrToRangeIpv4($ip, $mask);
                return ['cidr', $lower, $raw, $from, $to];
            }
        }

        // IP (IPv4)
        if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = $this->ipv4ToInt($raw);
            return ['ip', $lower, $raw, $n, $n];
        }

        // Email
        if (filter_var($lower, FILTER_VALIDATE_EMAIL)) {
            return ['email', $lower, $lower, null, null];
        }

        // Domain patterns: "@domain.com" or "*@domain.com" or "domain.com"
        $domain = $lower;
        if (str_starts_with($domain, '*@')) $domain = substr($domain, 2);
        if (str_starts_with($domain, '@')) $domain = substr($domain, 1);
        $domain = trim($domain);

        if ($domain !== '' && preg_match('/^[a-z0-9.-]+\\.[a-z]{2,}$/', $domain) === 1) {
            return ['domain', $domain, $domain, null, null];
        }

        // Fallback
        return ['unknown', $lower, $raw, null, null];
    }

    private function ipv4ToInt(string $ip): int
    {
        $long = ip2long($ip);
        if ($long === false) return 0;
        // convert to unsigned
        return (int) sprintf('%u', $long);
    }

    private function cidrToRangeIpv4(string $ip, int $mask): array
    {
        $ipLong = $this->ipv4ToInt($ip);
        $maskLong = $mask === 0 ? 0 : (~0 << (32 - $mask)) & 0xFFFFFFFF;
        $network = $ipLong & $maskLong;
        $broadcast = $network | (~$maskLong & 0xFFFFFFFF);
        return [(int) $network, (int) $broadcast];
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter_name' => 'required|string|max:120',
            'expired_at' => 'required|date|after_or_equal:now',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        [$type, $filterKey, $filterName, $ipFrom, $ipTo] = $this->normalizeFilter((string) $request->input('filter_name'));

        if ($type === 'unknown') {
            return response()->json([
                'status' => 1,
                'message' => 'Введите корректный IP, Email, домен или CIDR (например: 1.2.3.0/24)',
            ]);
        }

        $expiredAt = Carbon::parse((string) $request->input('expired_at'));
        $description = $request->filled('description')
            ? (string) $request->input('description')
            : null;

        // use filter_key for upsert (requires UNIQUE index)
        Banned::query()->updateOrCreate(
            ['filter_key' => $filterKey],
            [
                'type' => $type,
                'filter_key' => $filterKey,
                'filter_name' => $filterName,
                'description' => $description,
                'expired_at' => $expiredAt,
                'ip_from' => $ipFrom,
                'ip_to' => $ipTo,
            ]
        );

        return response()->json([
            'status' => 0,
            'message' => 'Фильтр сохранён',
        ]);
    }

    /**
     * Удаление фильтра
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $banned = Banned::query()->findOrFail($id);
        $label = (string) $banned->filter_name;
        $banned->delete();

        return response()->json([
            'status' => 0,
            'message' => $label . ' успешно удалён',
        ]);
    }
}
