<?php

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Models\Advantage;
use App\Models\Banner;
use App\Models\CheckboxAgreement;
use App\Models\CompetitorLink;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContestConditionModel;
use App\Models\ContestFaqModel;
use App\Models\Currency;
use App\Models\CurrencyFields;
use App\Models\CurrencyNotification;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeCity;
use App\Models\DirectionExchangeGroup;
use App\Models\DirectionField;
use App\Models\DirectionNotification;
use App\Models\ExtraField;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FileParserGroup;
use App\Models\FilterCurrency;
use App\Models\GroupParserExchange;
use App\Models\InfoStatistic;
use App\Models\LinksFooter;
use App\Models\LinksFooterGroup;
use App\Models\LinksReview;
use App\Models\LinksReviewGroup;
use App\Models\Menu;
use App\Models\NoticeExchange;
use App\Models\OrderStep;
use App\Models\PageGroup;
use App\Models\Partner;
use App\Models\RequisiteField;
use App\Models\RequisiteInfoField;
use App\Models\Reserve;
use App\Models\SelectorFee;
use App\Models\SocialAuthSystem;
use App\Models\SocialReview;
use App\Models\TaskStatus;
use App\Models\VerificationCardCategory;
use App\Models\VerificationCardInstruction;
use App\Support\Facades\iEXApp;
use Illuminate\Http\Request;

class SortingVueController extends Controller
{

    public function filterColumns(Request $request)
    {
        if($request->has('page'))
        {
            switch ($request->get('page'))
            {
                case 'order-logs-status':

                    $options = [
                        'pagination' => ['name' => 'admin_order_status_log_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_order_statuses_log_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'direction-exchange':

                    $options = [
                        'pagination' => ['name' => 'num_direction_paginate', 'type' => 'int'],
                        'columns' => ['name' => 'admin_directions_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'currencies':

                    $options = [
                        'pagination' => ['name' => 'admin_currencies_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_currencies_column_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'currency-codes':

                    $options = [
                        'pagination' => ['name' => 'admin_code_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_codes_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'requisites':

                    $options = [
                        'pagination' => ['name' => 'admin_requisites_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_requisites_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'reserves':
                    $options = [
                        'pagination' => ['name' => 'admin_reserves_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_reserves_column_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'users':
                    $options = [
                        'pagination' => ['name' => 'admin_user_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_user_hidden_columns', 'type' => 'string']
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'discount':
                    $options = [
                        'pagination' => ['name' => 'admin_bonuses_discount_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_bonuses_discount_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'payment-systems':
                    $options = [
                        'pagination' => ['name' => 'admin_payments_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_payment_systems_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'bestchange-courses':
                    $options = [
                        'pagination' => ['name' => 'admin_bestchange_parser_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_bestchange_parser_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'file-courses':
                    $options = [
                        'pagination' => ['name' => 'admin_file_parser_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_file_parser_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'competitor-courses':
                    $options = [
                        'pagination' => ['name' => 'admin_competitor_parser_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_competitor_parser_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;


                case 'sources-courses':
                    $options = [
                        'pagination' => ['name' => 'admin_crypto_parser_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_crypto_parser_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'formula-courses':
                    $options = [
                        'pagination' => ['name' => 'admin_parser_formula_paginate', 'type' => 'int'],
                        'columns' => ['name' => 'admin_parser_formula_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'auto-payments':
                    $options = [
                        'pagination' => ['name' => 'admin_autopayment_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_autopayment_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'merchants':
                    $options = [
                        'pagination' => ['name' => 'admin_merchant_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_merchant_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'telegram-notification':
                    $options = [
                        'pagination' => ['name' => 'admin_telegram_notification_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_telegram_notification_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;

                case 'verification-cards':
                    $options = [
                        'pagination' => ['name' => 'admin_verifications_card_pagination', 'type' => 'int'],
                        'columns' => ['name' => 'admin_verifications_card_hidden_columns', 'type' => 'string',]
                    ];

                    iEXApp::settingsColumns($request->filters, $options);
                    break;
            }
        }

        return response()->json([]);
    }

    /**
     * Сортировка данных
     */
    public function sortingDefault(Request $request)
    {
        if ($request->has('page')) {
            switch ($request->get('page')) {

                case 'page_groups':
                    foreach ($request->item as $key => $item) {
                        PageGroup::find($item)->update([
                            'sort_order' => $key,
                        ]);
                    }
                    break;

                case 'contact':
                    foreach ($request->item as $key => $item) {
                        Contact::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'menu':
                    foreach ($request->item as $key => $item) {
                        Menu::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'menu_children':
                    $parentId = $request->input('parent_id');

                    // Нельзя сортировать подкатегории без указания parent_id
                    if ($parentId === null) {
                        break;
                    }

                    // Сортировка подкатегорий конкретного родителя
                    foreach ($request->item as $key => $itemId) {
                        Menu::whereKey($itemId)
                            ->where('parent_id', $parentId)
                            ->update([
                                'sorting' => $key,
                            ]);
                    }
                    break;

                case 'faq':
                    foreach ($request->item as $key => $item) {
                        Faq::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'faq-category':
                    foreach ($request->item as $key => $item) {
                        FaqCategory::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }

                    break;
                case 'partners':
                    foreach ($request->item as $key => $item) {
                        Partner::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'notification':
                    foreach ($request->item as $key => $item) {
                        NoticeExchange::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'advantage':
                    foreach ($request->item as $key => $item) {
                        Advantage::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'banner':
                    foreach ($request->item as $key => $item) {
                        Banner::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'filter_currency':
                    foreach ($request->item as $key => $item) {
                        FilterCurrency::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'currency_fields_in':
                    foreach ($request->item as $key => $item) {
                        CurrencyFields::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'currency_fields_out':
                    foreach ($request->item as $key => $item) {
                        CurrencyFields::find($item)->update([
                            'sorting_out' => $key,
                        ]);
                    }
                    break;

                case 'sorting_reserves':
                    foreach ($request->item as $key => $item) {
                        Reserve::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'sorting_card_instruction':
                    foreach ($request->item as $key => $item) {
                        VerificationCardInstruction::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'sorting_card_category':
                    foreach ($request->item as $key => $item) {
                        VerificationCardCategory::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'checkbox-agreement':
                    foreach ($request->item as $key => $item) {
                        CheckboxAgreement::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'links_reviews_group':
                    foreach ($request->item as $key => $item) {
                        LinksReviewGroup::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'links_reviews':
                    foreach ($request->item as $key => $item) {
                        LinksReview::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'social_reviews':
                    foreach ($request->item as $key => $item) {
                        SocialReview::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'links_footers_group':
                    foreach ($request->item as $key => $item) {
                        LinksFooterGroup::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'links_footer':
                    foreach ($request->item as $key => $item) {
                        LinksFooter::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'auth_system':
                    foreach ($request->item as $key => $item) {
                        SocialAuthSystem::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'tools_statistics':
                    foreach ($request->item as $key => $item) {
                        InfoStatistic::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'currency_notification':
                    foreach ($request->item as $key => $item) {
                        CurrencyNotification::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'direction_notification':
                    foreach ($request->item as $key => $item) {
                        DirectionNotification::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'direction-selector-fees':
                    foreach ($request->item as $key => $item) {
                        SelectorFee::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'default_parser_sources':
                    foreach ($request->item as $key => $item) {
                        GroupParserExchange::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'competitor_link_sources':
                    foreach ($request->item as $key => $item) {
                        CompetitorLink::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'file_parser_group':
                    foreach ($request->item as $key => $item) {
                        FileParserGroup::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
                case 'direction_fields':
                    foreach ($request->item as $key => $item) {
                        DirectionField::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'extra_fields_user_order':
                    foreach ($request->item as $key => $itemId) {
                        ExtraField::query()
                            ->whereKey($itemId)
                            ->where('scope', 'user_order')
                            ->update([
                                'sorting' => $key,
                            ]);
                    }
                    break;
                case 'direction_groups':
                    foreach ($request->item as $key => $item) {
                        DirectionExchangeGroup::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'direction_exchange_in':
                    foreach ($request->item as $key => $item) {
                        Currency::find($item)->update([
                            'sorting_1' => $key,
                        ]);
                    }
                    break;

                case 'direction_exchange_out':
                    foreach ($request->item as $key => $item) {
                        DirectionExchange::find($item)->update([
                            'sorting_2' => $key,
                        ]);
                    }

                    break;

                case 'orders_steps':
                    foreach ($request->item as $key => $item) {
                        OrderStep::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;


                case 'direction_sorting_cities':
                    foreach ($request->item as $key => $item) {
                        DirectionExchangeCity::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;


                case 'contacts_group':
                    foreach ($request->item as $key => $item) {
                        ContactGroup::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'contests-conditions':
                    foreach ($request->item as $key => $item) {
                        ContestConditionModel::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'contests-faq':
                    foreach ($request->item as $key => $item) {
                        ContestFaqModel::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'order_status':
                    foreach ($request->item as $key => $item) {
                        TaskStatus::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'sorting_requisites_fields':
                    foreach ($request->item as $key => $item) {
                        RequisiteField::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;

                case 'sorting_requisites_info':
                    foreach ($request->item as $key => $item) {
                        RequisiteInfoField::find($item)->update([
                            'sorting' => $key,
                        ]);
                    }
                    break;
            }
        }
    }
}
