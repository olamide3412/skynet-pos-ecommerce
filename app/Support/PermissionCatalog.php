<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Map of modules to their permissions with human labels.
     */
    public const MODULES = [
        'Dashboard' => [
            'dashboard.view' => 'View operational back-office dashboard',
            'dashboard.view_financials' => 'View gross revenue, sales trends, and executive metrics',
        ],
        'Catalog' => [
            'products.view' => 'View product list and details',
            'products.create' => 'Create new products',
            'products.edit' => 'Edit products and pricing',
            'products.delete' => 'Delete products',
            'products.bulk_actions' => 'Perform bulk catalog actions',
            'products.view_cost_price' => 'View product wholesale cost and buying prices',
            'categories.manage' => 'Create, edit and delete categories and subcategories',
            'brands.manage' => 'Create, edit and delete product brands',
            'attributes.manage' => 'Manage product attributes and options',
            'reviews.moderate' => 'Moderate, approve and delete customer reviews',
        ],
        'Orders & Fulfillment' => [
            'orders.view' => 'View orders assigned to self or view order details',
            'orders.view_all' => 'View all customer and store orders enterprise-wide',
            'orders.update_status' => 'Update order fulfillment status (pending, processing, shipped, etc.)',
            'orders.cancel' => 'Cancel orders and restock inventory',
            'orders.print_invoice' => 'Print customer order invoices and packing slips',
            'orders.assign_staff' => 'Assign orders to riders or warehouse staff',
            'fulfillment.view_queue' => 'View fulfillment packing and dispatch queues',
            'fulfillment.pack' => 'Pick and pack items for shipping',
            'fulfillment.dispatch' => 'Handover parcels to dispatch riders or couriers',
            'inventory.view_stock_alerts' => 'View out-of-stock and low-stock inventory alerts',
        ],
        'Rider & POD Cash' => [
            'pod.view_assigned' => 'View orders assigned for delivery transit',
            'pod.collect_cash' => 'Record cash or transfer collected from customer at doorstep',
            'pod.remit_cash' => 'Record handover of collected cash to store cashier',
            'pod.reconcile' => 'Verify and reconcile physical cash in store drawer/till',
        ],
        'Discounts & Coupons' => [
            'discounts.manage' => 'Manage promotional markdowns and flash sales',
            'coupons.manage' => 'Create, edit and toggle coupon codes',
        ],
        'Reports' => [
            'reports.sales.view_own' => 'View sales report for own attributed orders',
            'reports.sales.view_all' => 'View comprehensive sales report for all staff and channels',
            'reports.sales.export' => 'Export sales reports to CSV / Excel',
            'reports.end_of_day' => 'View End-of-Day closing reports and daily reconciliations',
            'reports.revenue_profit' => 'View revenue analytics and profit margin reports',
        ],
        'POS Integration' => [
            'pos.view' => 'View POS integration dashboard and sync status',
            'pos.sync' => 'Trigger catalog and stock availability synchronization',
            'pos.import' => 'Import items from POS catalog into eCommerce store',
            'pos.manage_connection' => 'Configure POS connection credentials and API pairing',
        ],
        'Settings' => [
            'settings.store' => 'Update store settings, payment gateways, and policies',
            'settings.shipping_zones' => 'Manage delivery zones and shipping rates',
            'settings.themes' => 'Change storefront color palettes and theme styling',
            'settings.sliders' => 'Manage homepage promotional banners and sliders',
        ],
        'Referral Program' => [
            'affiliates.view' => 'View affiliates and referral statistics',
            'affiliates.manage' => 'Approve, reject, suspend affiliates and edit commission overrides',
            'affiliates.withdrawals' => 'Process and pay affiliate withdrawal requests',
        ],
        'User Management' => [
            'users.view' => 'View back-office staff and user accounts',
            'users.create' => 'Create new staff user profiles',
            'users.edit' => 'Edit user contact details and profile status',
            'users.disable' => 'Enable or disable staff accounts',
            'users.reset_password' => 'Reset passwords for staff accounts',
        ],
        'System Access' => [
            'backoffice.access' => 'Login and access the administration back-office',
        ],
    ];

    /**
     * Default role templates with their assigned permissions.
     */
    public const DEFAULT_ROLES = [
        'Administrator' => [
            'description' => 'Store administrator with full managerial and financial privileges.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view', 'dashboard.view_financials',
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.bulk_actions', 'products.view_cost_price',
                'categories.manage', 'brands.manage', 'attributes.manage', 'reviews.moderate',
                'orders.view', 'orders.view_all', 'orders.update_status', 'orders.cancel', 'orders.print_invoice', 'orders.assign_staff',
                'fulfillment.view_queue', 'fulfillment.pack', 'fulfillment.dispatch', 'inventory.view_stock_alerts',
                'pod.view_assigned', 'pod.collect_cash', 'pod.remit_cash', 'pod.reconcile',
                'discounts.manage', 'coupons.manage',
                'reports.sales.view_own', 'reports.sales.view_all', 'reports.sales.export', 'reports.end_of_day', 'reports.revenue_profit',
                'pos.view', 'pos.sync', 'pos.import', 'pos.manage_connection',
                'affiliates.view', 'affiliates.manage', 'affiliates.withdrawals',
                'settings.store', 'settings.shipping_zones', 'settings.themes', 'settings.sliders',
                'users.view', 'users.create', 'users.edit', 'users.disable', 'users.reset_password',
            ],
        ],

        'Store Manager' => [
            'description' => 'Daily store operations: managing catalog, orders, promotions, fulfillment, and daily closing.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view',
                'products.view', 'products.create', 'products.edit', 'products.bulk_actions',
                'categories.manage', 'brands.manage', 'attributes.manage', 'reviews.moderate',
                'orders.view', 'orders.view_all', 'orders.update_status', 'orders.cancel', 'orders.print_invoice', 'orders.assign_staff',
                'fulfillment.view_queue', 'fulfillment.pack', 'fulfillment.dispatch', 'inventory.view_stock_alerts',
                'pod.view_assigned', 'pod.collect_cash', 'pod.remit_cash', 'pod.reconcile',
                'discounts.manage', 'coupons.manage',
                'reports.sales.view_own', 'reports.sales.view_all', 'reports.sales.export', 'reports.end_of_day',
                'pos.view', 'pos.sync', 'pos.import',
                'users.view',
            ],
        ],

        'Sales / Cashier' => [
            'description' => 'Front-desk orders, customer service, booking manual orders, and handling payments.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view',
                'products.view',
                'orders.view', 'orders.view_all', 'orders.update_status', 'orders.print_invoice',
                'pod.view_assigned', 'pod.collect_cash', 'pod.remit_cash',
                'reports.sales.view_own', 'reports.end_of_day',
            ],
        ],

        'Warehouse / Fulfillment' => [
            'description' => 'Picking, packing, inventory tracking, dispatch handover, and stock level monitoring.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view',
                'products.view',
                'orders.view', 'orders.view_all', 'orders.update_status', 'orders.print_invoice',
                'fulfillment.view_queue', 'fulfillment.pack', 'fulfillment.dispatch', 'inventory.view_stock_alerts',
                'pod.view_assigned',
            ],
        ],

        'Rider / Dispatch' => [
            'description' => 'Delivery transit, doorstep customer delivery, collecting POD cash, and remitting cash to store.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view',
                'orders.view',
                'pod.view_assigned', 'pod.collect_cash', 'pod.remit_cash',
                'reports.sales.view_own',
            ],
        ],

        'Catalog Manager' => [
            'description' => 'Product listings, descriptions, categories, attributes, reviews, and POS catalog imports.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view',
                'products.view', 'products.create', 'products.edit', 'products.delete', 'products.bulk_actions',
                'categories.manage', 'brands.manage', 'attributes.manage', 'reviews.moderate',
                'pos.view', 'pos.import',
            ],
        ],

        'Accountant / Finance' => [
            'description' => 'Financial reporting, revenue analytics, profit margins, cost analysis, and POD cash drawer reconciliation.',
            'permissions' => [
                'backoffice.access',
                'dashboard.view', 'dashboard.view_financials',
                'products.view', 'products.view_cost_price',
                'orders.view', 'orders.view_all', 'orders.print_invoice',
                'pod.reconcile',
                'reports.sales.view_own', 'reports.sales.view_all', 'reports.sales.export', 'reports.end_of_day', 'reports.revenue_profit',
            ],
        ],
    ];

    /**
     * Get flat list of all permission names.
     */
    public static function allPermissionNames(): array
    {
        $all = [];
        foreach (self::MODULES as $perms) {
            foreach ($perms as $permName => $label) {
                $all[] = $permName;
            }
        }
        return $all;
    }
}

