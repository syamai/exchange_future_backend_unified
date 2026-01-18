<?php

return [

    /*
     * The output path for the generated documentation.
     * This path should be relative to the root of your application.
     */

    'output' => 'public/api/docs',

    /*
     * The router to be used (Laravel or Dingo).
     */
    'router' => 'laravel',

    /*
     * Generate a Postman collection in addition to HTML docs.
     */
    'postman' => [
        /*
         * Specify whether the Postman collection should be generated.
         */
        'enabled' => false,

        /*
         * The name for the exported Postman collection. Default: config('app.name')." API"
         */
        'name' => null,

        /*
         * The description for the exported Postman collection.
         */
        'description' => null,
    ],

    /*
     * The routes for which documentation should be generated.
     * Each group contains rules defining which routes should be included ('match', 'include' and 'exclude' sections)
     * and rules which should be applied to them ('apply' section).
     */
    'routes' => [
        [
            /*
             * Specify conditions to determine what routes will be parsed in this group.
             * A route must fulfill ALL conditions to pass.
             */
            'match' => [

                /*
                 * Match only routes whose domains match this pattern (use * as a wildcard to match any characters).
                 */
                'domains' => [
                    '*',
                    // 'domain1.*',
                ],

                /*
                 * Match only routes whose paths match this pattern (use * as a wildcard to match any characters).
                 */
                'prefixes' => [
//                    '*',
                    'api/v1/user',
                    'api/v1/balances/*',
                    'api/v1/orders/pending',
                    'api/v1/orders/pending-all',
                    'api/v1/orders/transactions',
                    'api/v1/orders/trading-histories',
                    'api/v1/orders',
                    'api/v1/withdraw',
                    'api/v1/orders/*/cancel',
                    'api/v1/orders/cancel-by-type',
                    'api/v1/orders/cancel-all',
                    'api/v1/my-order-transactions',
                    'api/v1/price-scope',
                    'api/v1/prices*,',
                    'api/v1/transactions/withdraw/total-usd-pending-withdraw',
                    'api/v1/transactions/withdraw/total-pending-withdraw',
                    'api/v1/balance/{currency}',
                    // 'api/v1/hmac-tokens*',
                    'api/v1/margin/instrument/all',
                    'api/v1/margin/instrument/active',
                    'api/v1/margin/instrument/indices/{symbol?}',
                    'api/v1/margin/instrument/indices-active',
                    'api/v1/margin/instrument/risk-limit-list',
                    'api/v1/margin/instrument/{symbol?}',
                    'api/v1/margin/positions',
                    'api/v1/margin/update-leverage',
                    'api/v1/margin/update-margin',
                    'api/v1/margin/update-risk-limit',
                    'api/v1/margin/balance',
                    'api/v1/margin/order/',
                    'api/v1/margin/order/get-active',
                    'api/v1/margin/order/stops',
                    'api/v1/margin/order/fills',
                    'api/v1/margin/order/create',
                    'api/v1/margin/order/cancel-active-order',
                    'api/v1/margin/trade',
                    'api/v1/margin/trade/recent',
                    'api/v1/margin/orderbook',
                    'api/v1/margin/settlement',
                    'api/v1/margin/funding',
                    'api/v1/margin/insurance',
                    'api/v1/margin/composite-index',
                    // 'users/*',
                ],

                /*
                 * Match only routes registered under this version. This option is ignored for Laravel router.
                 * Note that wildcards are not supported.
                 */
                'versions' => [
                    'v1',
                ],
            ],

            /*
             * Include these routes when generating documentation,
             * even if they did not match the rules above.
             * Note that the route must be referenced by name here (wildcards are supported).
             */
            'include' => [
                // 'users.index', 'healthcheck*'
            ],

            /*
             * Exclude these routes when generating documentation,
             * even if they matched the rules above.
             * Note that the route must be referenced by name here (wildcards are supported).
             */
            'exclude' => [
                // 'users.create', 'admin.*'
            ],

            /*
             * Specify rules to be applied to all the routes in this group when generating documentation
             */
            'apply' => [
                /*
                 * Specify headers to be added to the example requests
                 */
                'headers' => [
                    'Sec-Fetch-Mode' => 'cors',
                    'Accept' => 'application/json',
                    'APIKEY' => '6d38847e7587f1fb5064343c2b3f61675f96be92fb19f7087912e8ce89d212956d436b0c02307dcb',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36'
                ],

                /*
                 * If no @response or @transformer declarations are found for the route,
                 * we'll try to get a sample response by attempting an API call.
                 * Configure the settings for the API call here.
                 */
                'response_calls' => [
                    /*
                     * API calls will be made only for routes in this group matching these HTTP methods (GET, POST, etc).
                     * List the methods here or use '*' to mean all methods. Leave empty to disable API calls.
                     */
                    'methods' => ['GET'],

                    /*
                     * For URLs which have parameters (/users/{user}, /orders/{id?}),
                     * specify what values the parameters should be replaced with.
                     * Note that you must specify the full parameter,
                     * including curly brackets and question marks if any.
                     *
                     * You may also specify the preceding path, to allow for variations,
                     * for instance 'users/{id}' => 1 and 'apps/{id}' => 'htTviP'.
                     * However, there must only be one parameter per path.
                     */
                    'bindings' => [
                        // '{user}' => 1,
                        '{store}' => true
                    ],

                    /*
                     * Laravel config variables which should be set for the API call.
                     * This is a good place to ensure that notifications, emails
                     * and other external services are not triggered
                     * during the documentation API calls
                     */
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                        // 'service.key' => 'value',
                    ],

                    /*
                     * Headers which should be sent with the API call.
                     */
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {token}',
                    ],

                    /*
                     * Cookies which should be sent with the API call.
                     */
                    'cookies' => [
                        // 'name' => 'value'
                    ],

                    /*
                     * Query parameters which should be sent with the API call.
                     */
                    'query' => [
                        // 'key' => 'value',
                    ],

                    /*
                     * Body parameters which should be sent with the API call.
                     */
                    'body' => [
                        // 'key' => 'value',
                    ],
                ],
            ],
        ],
        [
            'match' => [
                'domains' => [
                    '*',
                ],
                'prefixes' => [
                    'api/v1/oauth/token',
                    'api/v1/orders/order-book',
                    'api/v1/orders/transactions/recent',
                    'api/v1/order-transaction-latest',
                    'api/v1/get-trades-by-order-id/{id}',
                ],
                'versions' => [
                    'v1',
                ],
            ],
            'include' => [],
            'exclude' => [],
            'apply' => [

                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36'
                ],
                'response_calls' => [
                    'methods' => ['GET'],
                    'bindings' => [
                        // '{user}' => 1,
                        '{store}' => true
                    ],
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                        // 'service.key' => 'value',
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {token}',
                    ],
                ],
            ],
        ],
        [
            'match' => [
                'domains' => [
                    '*',
                ],
                'prefixes' => [
                    'api/v1/transactions/{currency?}',
                    'api/v1/transfer-balance'
                ],
                'versions' => [
                    'v1',
                ],
            ],
            'apply' => [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36'
                ],
                'response_calls' => [
                    'methods' => ['GET'],
                    'bindings' => [
                        '{store}' => true
                    ],
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {token}',
                    ],
                    'cookies' => [],
                    'query' => [],
                    'body' => [],
                ],
            ],
        ],
        [
            'exclude' => ['deposit.create-address'],
            'match' => [
                'domains' => [
                    '*',
                ],
                'prefixes' => [
                    // 'api/v1/mam/*',
                    // 'api/v1/deposit-address',
                ],
                'versions' => [
                    'v1',
                ],
            ],
            'include' => [],
            'exclude' => [],
            'apply' => [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36'
                ],
                'response_calls' => [
                    'methods' => ['GET'],
                    'bindings' => [
                        '{store}' => true
                    ],
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer {token}',
                    ],
                    'cookies' => [],
                    'query' => [],
                    'body' => [],
                ],
            ],
        ],
    ],
    'logo' => 'https://testnet.amanpuri.io/images/logo/logo-amanpuri-full.png',
    'default_group' => 'general',
    'example_languages' => [
        'bash',
        'javascript',
        'php'
    ],
    'fractal' => [
        'serializer' => null,
    ],
];
