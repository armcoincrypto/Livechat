<?php

return [

    'version' => '1.0',

    'prefix' => env('TELEGRAM_SECURITY_PREFIX', null),

    'bots' => [

        'iex' => [
            'token' => '',
            'commands' => [
                \iEXPackages\TelegramBot\Commands\StartCommand::class,
                \iEXPackages\TelegramBot\Commands\LanguageCommand::class
            ],
        ]


//        'iex' => [
//            'username' => env('TELEGRAM_BOT_NAME', null),
//            'token' => env('TELEGRAM_BOT_TOKEN', null),
//            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', 'YOUR-CERTIFICATE-PATH'),
//            'webhook_url' => env('TELEGRAM_WEBHOOK_URL', null),
//            'commands' => [
//                \App\Telegram\Commands\StartCommand::class,
//                \App\Telegram\Commands\ReservesCommand::class,
//                \App\Telegram\Commands\AboutCommand::class,
//                \App\Telegram\Commands\SupportCommand::class,
//                \App\Telegram\Commands\CheckCommand::class,
//                \App\Telegram\Commands\HelpCommand::class,
//                \App\Telegram\Commands\CreateReviewCommand::class,
//                \App\Telegram\Commands\PopularDirectionCommand::class,
//                \App\Telegram\Commands\ExchangeCommand::class,
//                \App\Telegram\Commands\CabinetCommand::class,
//                \App\Telegram\Commands\ConnectCommand::class,
//                \App\Telegram\Commands\OrdersCommand::class,
//                \App\Telegram\Commands\TariffsCommand::class,
//                \App\Telegram\Commands\LanguageCommand::class,
//                \App\Telegram\Commands\MonitoringCommand::class,
//                \App\Telegram\Commands\ContestsCommand::class,
//                \App\Telegram\Commands\ReviewCommand::class,
//            ],
//        ],
//        'admin' => [
//            'username' => env('ADMIN_TELEGRAM_BOT_NAME', null),
//            'token' => env('ADMIN_TELEGRAM_BOT_TOKEN', null),
//            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', null),
//            'webhook_url' => '',
//            'commands' => [
//                \App\Telegram\AdminCommands\HelpCommand::class,
//                \App\Telegram\AdminCommands\StartCommand::class,
//                \App\Telegram\AdminCommands\CommonReserveCommand::class,
//                \App\Telegram\AdminCommands\StatisticsCommand::class,
//                \App\Telegram\AdminCommands\OnCommand::class,
//                \App\Telegram\AdminCommands\OffCommand::class,
//            ],
//        ],
//        'notice' => [
//            'username' => env('NOTICE_TELEGRAM_BOT_NAME', null),
//            'token' => env('NOTICE_TELEGRAM_BOT_TOKEN', null),
//            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', null),
//            'webhook_url' => '',
//            'commands' => [
//
//            ],
//        ],
    ],

    'bot_token' => env('TELEGRAM_BOT_TOKEN', 'YOUR-BOT-TOKEN'),
    'bot_name' => env('TELEGRAM_BOT_NAME', 'YOUR-BOT-NAME'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Default Bot Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the bots you wish to use as
    | your default bot for regular use.
    |
    */
    'default' => 'iex',

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Requests [Optional]
    |--------------------------------------------------------------------------
    |
    | When set to True, All the requests would be made non-blocking (Async).
    |
    | Default: false
    | Possible Values: (Boolean) "true" OR "false"
    |
    */
    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Handler [Optional]
    |--------------------------------------------------------------------------
    |
    | If you'd like to use a custom HTTP Client Handler.
    | Should be an instance of \Telegram\Bot\HttpClients\HttpClientInterface
    |
    | Default: GuzzlePHP
    |
    */
    'http_client_handler' => null,

    /*
    |--------------------------------------------------------------------------
    | Resolve Injected Dependencies in commands [Optional]
    |--------------------------------------------------------------------------
    |
    | Using Laravel's IoC container, we can easily type hint dependencies in
    | our command's constructor and have them automatically resolved for us.
    |
    | Default: true
    | Possible Values: (Boolean) "true" OR "false"
    |
    */
    'resolve_command_dependencies' => true,

    /*
    |--------------------------------------------------------------------------
    | Register Telegram Global Commands [Optional]
    |--------------------------------------------------------------------------
    |
    | If you'd like to use the SDK's built in command handler system,
    | You can register all the global commands here.
    |
    | Global commands will apply to all the bots in system and are always active.
    |
    | The command class should extend the \Telegram\Bot\Commands\Command class.
    |
    | Default: The SDK registers, a help command which when a user sends /help
    | will respond with a list of available commands and description.
    |
    */
    'commands' => [
        //  Telegram\Bot\Commands\HelpCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Groups [Optional]
    |--------------------------------------------------------------------------
    |
    | You can organize a set of commands into groups which can later,
    | be re-used across all your bots.
    |
    | You can create 4 types of groups:
    | 1. Group using full path to command classes.
    | 2. Group using shared commands: Provide the key name of the shared command
    | and the system will automatically resolve to the appropriate command.
    | 3. Group using other groups of commands: You can create a group which uses other
    | groups of commands to bundle them into one group.
    | 4. You can create a group with a combination of 1, 2 and 3 all together in one group.
    |
    | Examples shown below are by the group type for you to understand each of them.
    */
    'command_groups' => [
        /* // Group Type: 1
           'commmon' => [
                Acme\Project\Commands\TodoCommand::class,
                Acme\Project\Commands\TaskCommand::class,
           ],
        */

        /* // Group Type: 2
           'subscription' => [
                'start', // Shared Command Name.
                'stop', // Shared Command Name.
           ],
        */

        /* // Group Type: 3
            'auth' => [
                Acme\Project\Commands\LoginCommand::class,
                Acme\Project\Commands\SomeCommand::class,
            ],

            'stats' => [
                Acme\Project\Commands\UserStatsCommand::class,
                Acme\Project\Commands\SubscriberStatsCommand::class,
                Acme\Project\Commands\ReportsCommand::class,
            ],

            'admin' => [
                'auth', // Command Group Name.
                'stats' // Command Group Name.
            ],
        */

        /* // Group Type: 4
           'myBot' => [
                'admin', // Command Group Name.
                'subscription', // Command Group Name.
                'status', // Shared Command Name.
                'Acme\Project\Commands\BotCommand' // Full Path to Command Class.
           ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Commands [Optional]
    |--------------------------------------------------------------------------
    |
    | Shared commands let you register commands that can be shared between,
    | one or more bots across the project.
    |
    | This will help you prevent from having to register same set of commands,
    | for each bot over and over again and make it easier to maintain them.
    |
    | Shared commands are not active by default, You need to use the key name to register them,
    | individually in a group of commands or in bot commands.
    | Think of this as a central storage, to register, reuse and maintain them across all bots.
    |
    */
    'shared_commands' => [
        // 'start' => Acme\Project\Commands\StartCommand::class,
        // 'stop' => Acme\Project\Commands\StopCommand::class,
        // 'status' => Acme\Project\Commands\StatusCommand::class,
    ],

    'connections' => [
        'host' => env('DB_TELEGRAM_HOST'),
        'username' => env('DB_TELEGRAM_USERNAME'),
        'database' => env('DB_TELEGRAM_DATABASE'),
        'password' => env('DB_TELEGRAM_PASSWORD'),
    ],
];
