<?php

return [

    'lang' => [
        'ru' => '🇷🇺 Русcкий',
        'en' => '🇬🇧 English',
    ],

    'start_commands' => [
        'exchange' => '🔄 Exchange',
        'reserves' => '🏦 Reserves',
        'cabinet' => '🖥 My cabinet',
        'create_review' => '😊 Create review',
        'tariffs' => '🗃 Tariffs',
        'about' => '🚀 About',
        'orders' => '🗒 Orders',
        'connect' => '🔄 Synchronization',
        'lang' => '🌎 Language',
    ],

    'commands' => [
        'start' => 'Запуск Telegram бота',
        'about' => 'Информация о боте',
        'cabinet' => 'Личный кабинет',
        'exchange' => 'Начать обмены',
        'create_review' => 'Написать отзыв',
        'orders' => 'Список заявок',
        'tariffs' => 'Тарифы',
        'reserve_request' => 'Запрос резерва',
        'check' => 'Поиск заявок по HASH',
        'reserves' => 'Список резервов',
        'popular_direction' => 'Популярные направления',
        'support' => 'Поддержка',
        'lang' => 'Язык бота',
        'monitoring' => 'Информация для мониторингов',
    ],

    'buttons' => [
        'rules' => 'Terms of use',
        'link_blockchain' => 'Link to Blockchain',
    ],

    'keyboards' => [
        'exit' => '🚪 Exit',
        'back' => '↩ Back',
        'condition' => 'ℹ️ Terms',
        'orders' => '🗒 Orders',
        'referral_program' => '👪 Referrals program',
        'synchronization' => '🔄 Synchronization',
        'refund_auth' => '🤷‍♂️️ Re-authorization',
        'positive' => '👍 Positive',
        'negative' => '👎 Negative',
        'review_old_client' => 'Feedback from other clients',
        'cancel' => '⭕️Cancel',
        'all_done' => '✅ All right',
        'i_pay' => '✅ I paid',
        'merchant_pay' => '💵 Proceed to checkout',
        'cancel_pay' => '❌ Reject the order',
        'redirect_site' => 'Go to website :name',
    ],

    'messages' => [

        'all_help_commands' => '<b>List of available commands</b>',
        'start_text' => '👋 Welcome to the bot of one of the best e-currency exchange services',
        'start_text_1' => 'Thank you for choosing our service. 🙏',
        'start_text_2' => 'To start the exchange - select the appropriate item in the menu 📝',

        'about_text' => 'Hi, I am the official telegram bot :name!',
        'version_bot' => '🧪 Telegram bot version: :version',

        'referral_program_title' => 'Referral program',
        'your_referral_link' => 'Your referral link',

        'auth_text' => 'You are logged in as :name',
        'auth_failed' => 'You are not authorized',

        'enter_email' => '👇 Enter your E-mail:',
        'enter_password' => '👇 Enter password: 
<i>(For security, delete the message with the password after logging into your account)</i>',
        'validate_fail_password' => '❌ Password is incorrect.',
        'validate_auth_failed' => '❌ Incorrect email or password entered',

        'review_work_service' => '💡 Please rate the work of our service.',
        'write_review_message' => '📝 What kind of review do you want to write?',
        'review_enter_comment' => '👇 Tell us what you think about our service:',
        'review_comment_failed' => '❌ The text is specified incorrectly (min. 3, max. 500 characters).',
        'enter_name' => '👇 Enter your name:',
        'review_name_failed' => '❌ The name is incorrect (min. 2, max. 50 characters).',
        'validate_email_failed' => '❌ E-mail is specified incorrectly (min. 5, max. 50 characters).',
        'review_done' => '✅ Feedback has been sent. Thank you for sharing your opinion. Once the review is approved, you will receive a notification',

        'in_currency_text' => 'Choose the currency you want to exchange 👇',
        'wrong_currency_id' => '❌ The currency is specified incorrectly (you must select from the list).',

        'out_currency_text' => 'You give: <b>:name</b>
Now indicate the currency you want to receive',
        'your_created_direction' => 'You create a request in the direction:',
        'exchange_rate' => '📈 Exchange at the rate',
        'reserve' => '💰 In reserve',
        'exchange_min_max' => 'Write me the amount you want to exchange from <b>:min_in</b> to <b>:max_in</b>',
        'enter_in_amount' => '👇 Specify the amount to be given:',
        'wrong_in_amount' => '⚠️ Be a little more careful!
        I cannot exchange this amount now.',
        'in_label' => 'I give',
        'exchange_enter_email' => '👇 Write an Email in the format "example@mail.com"',
        'enter_phone' => '👇 Write your phone number:',
        'wrong_phone' => '❌ The phone number is incorrect.',
        'undefined_field' => 'Undefined',
        'enter_field' => '👇 Please enter :name',
        'wrong_base' => '❌ Data is incorrect',
        'order_fail' => '❌ Exchange request canceled.',
        'wrong_error' => 'There were errors on the server, please try again',
        'wrong_error_order' => '❌ A transaction confirmation error has occurred.',
        'order_cancel' => '❌ The order was successfully canceled.',

        'confirm_order_text' => 'Now let\'s check that we are doing everything right!',
        'confirm_order_title' => 'The exchange will be made',
        'course' => 'By course',
        'order_give' => 'You give',
        'order_receiving' => 'You get',
        'order_confirm_info' => 'If there are no errors, click <b>That\'s right</b> 👇',

        'my_order_title' => 'Exchange on request',
        'my_order_course' => 'Exchange rate',
        'my_order_from_shot' => 'Sender details',
        'my_order_to_shot' => 'Recipient details',

        'detail_order_title' => 'Transaction information №:id',

        'order_pay_text' => 'Great, I have already created a request for an exchange <b>№:id</b>, there are very few left ...',
        'order_pay_confirm' => 'Make a transfer to the above details and click on the <b>I paid</b> button below this message.',
        'order_pay_confirm_merchant' => 'Click <b>Go to checkout</b> to go to the checkout page.',
        'direction_amount' => 'Direction and amount',
        'you_requisites' => 'Your details',
        'requisites_pay' => 'Payment details',
        'shot_undefined' => 'Account not defined',

        'warning_title' => 'Note!',

        'status' => 'Status',
        'created_at' => 'Created',
        'currency' => 'Currency',
        'amount' => 'Amount',

        'success_order' => '🕒 Your application has been successfully created. After processing the application, you will receive a message.',
        'order_id' => 'Order number',
        'order_status' => 'Status of your order',

        'support_title' => 'Support:   👇',
        'order_empty' => 'The list of orders is empty',
        'reserve_text' => '💡 Please let us know if there is not enough reserve for your exchange.',
        'enter_reserve_currency' => '👇 Select the currency you want to reserve:',
        'enter_reserve_amount_text' => '❇️ Currency: :currency
        🏦 Available reserve: :reserve',
        'enter_reserve_amount' => '👇 Specify the required amount of the reserve for the exchange:',
        'wrong_reserve_amount' => '❌ Amount is incorrect or less than available reserve (min. :min).',
        'reserve_request_done' => '✅ Reserve request sent. As soon as the reserve is available, you will receive a notification to the specified e-mail.',

        'enter_tariff' => '👇 Please select a currency to receive rates',
        'tariffs_text' => '💡 Tariffs: :name',

        'check_text' => '💡 Tracking the exchange request, you will find out its status and detailed information.',
        'enter_order_id' => '👇 Enter the order number:',
        'wrong_order_id' => '❌ The exchange request number is incorrect.',
        'order_notfound' => '❌ Order not found.',

        'enter_language' => '👇 Select the language you want to use:',
        'wrong_language' => '❌ The language is specified incorrectly (you must select from the list).',
        'change_language_success' => '✅ The bot language has been successfully changed.',

        'export_text' => 'МWe are always glad to new partners! We have a single referral system for all monitoring.',
    ],

];
