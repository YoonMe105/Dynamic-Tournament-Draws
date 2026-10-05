<?php

/*
|--------------------------------------------------------------------------
| Online payment (Stripe Checkout) for the T_Software platform fee
|--------------------------------------------------------------------------
| Put your Stripe secret key here (Stripe Dashboard -> Developers -> API keys).
|   - a test key (sk_test_...) takes test card payments only
|   - a live key (sk_live_...) takes real money
| Payment is by card (Visa, Mastercard, ...). The card details are typed on
| Stripe's own secure page - they never reach this server.
|
| Leave it empty to use TEST MODE: the payment page then shows a pretend
| checkout so the whole flow can be tried without a Stripe account.
| Never put a live key in a public repository.
*/

$stripeSecretKey = '';

// Payment methods offered on the Stripe page
$stripePaymentMethods = ['card'];
