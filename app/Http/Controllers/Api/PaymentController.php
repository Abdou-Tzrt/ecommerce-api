<?php

namespace App\Http\Controllers\Api;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class PaymentController extends Controller
{
    // construct
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPayment(Request $request, $order)
    {
        // Validate the request
        $request->validate([
            'provider' => 'required|in:' . implode(',', PaymentProvider::values()),
        ]);

        // check if the order belongs to the user authenticated
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // check if the order can be accept 
        if (!$order->canAcceptPayment()) {
            return response()->json([
                "message" => "This order cannot be paid"
            ], 400);
        }

        $provider = PaymentProvider::from($request->input('provider'));
        if($provider === PaymentProvider::STRIPE){
            return $this->createStripePayment($order);
        } else {
            return response()->json([
                "message" => "Payment provider not supported",
            ], 501);
        }
    }

    protected function createStripePayment(Order $order)
    {
        try {
            // Create a payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'provider' => PaymentProvider::STRIPE,
                'amount' => $order->total,
                'currency' => 'usd',
                'status' => PaymentStatus::PENDING,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'created_at' => now()->toIso8601String(),
                ]
            ]);

            // Create a payment intent using Stripe
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($order->total * 100), // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_id' => $payment->id
                ],
                'description' => "Payment for Order #{$order->order_number}",
            ]);

            // Update payment record with payment intent ID
            $payment->update([
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'client_secret' => $paymentIntent->client_secret
                ]),
            ]);

            // Return payment details to frontend
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'client_secret' => $paymentIntent->client_secret,
                'publishable_key' => config('services.stripe.key'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function confirmPayment(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        // Check payment ownership
        if ($payment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. This payment does not belong to you.'
            ], 403);
        }

        return response()->json([
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook.secret');

        try {
            // Verify the webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            
            // Handle different event types
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handleSuccessfulPayment($event->data->object);
                
                case 'payment_intent.payment_failed':
                    return $this->handleFailedPayment($event->data->object);
                
                default:
                    Log::info('Unhandled Stripe webhook: ' . $event->type);
                    return response()->json(['status' => 'ignored']);
            }
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Invalid webhook payload: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Invalid webhook signature: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }
    }


    protected function handleSuccessfulPayment($paymentIntent)
    {
        // Find payment by payment intent ID
        $payment = Payment::where('payment_intent_id', $paymentIntent->id)->first();
        
        // If no payment found, try to find it in metadata
        if (!$payment && isset($paymentIntent->metadata->payment_id)) {
            $payment = Payment::find($paymentIntent->metadata->payment_id);
        }
        
        if (!$payment) {
            Log::error("Payment not found for intent: " . $paymentIntent->id);
            return response()->json(['status' => 'payment-not-found']);
        }
        
        // Only process if payment is not already completed
        if ($payment->status !== PaymentStatus::COMPLETED) {
            // Mark payment as completed
            $payment->markAsCompleted($paymentIntent->id, [
                'stripe_data' => [
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'payment_method' => $paymentIntent->payment_method,
                    'status' => $paymentIntent->status,
                    'completed_at' => now()->toIso8601String(),
                ]
            ]);
            
            Log::info("Payment {$payment->id} marked as completed via webhook");
        }
        
        return response()->json(['status' => 'success']);
    }

    protected function handleFailedPayment($paymentIntent)
    {
        // Find payment by payment intent ID
        $payment = Payment::where('payment_intent_id', $paymentIntent->id)->first();
        
        // If no payment found, try to find it in metadata
        if (!$payment && isset($paymentIntent->metadata->payment_id)) {
            $payment = Payment::find($paymentIntent->metadata->payment_id);
        }
        
        if (!$payment) {
            Log::error("Payment not found for failed intent: " . $paymentIntent->id);
            return response()->json(['status' => 'payment-not-found']);
        }
        
        // Only mark as failed if not already in a final state
        if (!$payment->isFinal()) {
            // Mark payment as failed
            $payment->markAsFailed([
                'stripe_data' => [
                    'error' => $paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Unknown error',
                    'status' => $paymentIntent->status,
                    'failed_at' => now()->toIso8601String(),
                ]
            ]);
            
            Log::info("Payment {$payment->id} marked as failed via webhook");
        }
        
        return response()->json(['status' => 'success']);
    }
}
