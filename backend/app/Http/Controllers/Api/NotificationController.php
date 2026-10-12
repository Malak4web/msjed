<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                ...$n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at,
            ]),
            'unread' => $request->user()->unreadNotifications()->count(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'كل الإشعارات اتقرت', 'unread' => 0]);
    }

    /** المفتاح العام اللي المتصفح محتاجه عشان يشترك في الـ Push */
    public function pushKey(Request $request): JsonResponse
    {
        return response()->json([
            'key' => config('services.webpush.public_key'),
            'devices' => PushSubscription::where('user_id', $request->user()->id)->count(),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        PushSubscription::where('endpoint', $data['endpoint'])->delete();
        PushSubscription::create([
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
        ]);

        return response()->json(['message' => 'تم تفعيل الإشعارات على الجهاز ده'], 201);
    }

    /** إشعار تجريبي لكل أجهزة المستخدم — بيرجّع نتيجة كل جهاز عشان نعرف المشكلة فين بالظبط */
    public function test(Request $request): JsonResponse
    {
        $subs = PushSubscription::where('user_id', $request->user()->id)->get();

        if ($subs->isEmpty()) {
            return response()->json([
                'message' => 'مفيش أي جهاز مفعّل عليه الإشعارات لحسابك. اضغط "تفعيل" من الموبايل الأول.',
                'devices' => 0,
                'results' => [],
            ], 422);
        }

        $results = WebPushChannel::deliver($subs, [
            'title' => 'إشعار تجريبي ✓',
            'body' => 'تمام! الإشعارات شغالة على الجهاز ده — '.now()->format('H:i'),
            'url' => '/notifications',
            'tag' => 'test-'.now()->timestamp,
        ]);

        $ok = collect($results)->where('ok', true)->count();

        return response()->json([
            'message' => $ok ? "اتبعت لـ {$ok} من ".count($results).' جهاز. لو موصلش خلال ثواني، راجع إعدادات الإشعارات في الموبايل.' : 'الإرسال فشل لكل الأجهزة — شوف السبب تحت.',
            'devices' => count($results),
            'results' => $results,
        ], $ok ? 200 : 502);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint', $request->input('endpoint'))->delete();

        return response()->json(['message' => 'تم إيقاف الإشعارات على الجهاز ده']);
    }
}
