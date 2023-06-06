<?php

use Illuminate\Support\Facades\Route;

Route::middleware(["force-json"])->group(function() {
    // ************** 待删除 开始 **************
    Route::get("user/contract", function () {
        return '';
    });
    // ************** 待删除 结束 **************

    Route::post("picture/ticket", "PictureController@ticket")->name("picture.ticket");
    Route::post("picture/xunfei/yyzz", "PictureController@xunfei_yyzz")->name("picture.xunfei_yyzz");
    // *注册、登录验证码
    Route::post("code", "CommonController@getVerifyCode")->name("code");
    // *中台注册
    Route::post("auth/register", "AuthController@register");
    // *中台登录
    Route::post("auth/login", "AuthController@login");
    // *中台登录[移动端]
    Route::post("m/auth/login", "AuthController@loginFromMobile");

    // 模拟接单建店
    Route::post("arrange/{order}", "TestController@arrange");
    Route::post("shopStatus/{shop}", "TestController@shopStatus");
    // 同步订单
    Route::get("order/sync", "OrderController@sync2")->name("api.order.sync");
    // 取消订单
    Route::any("order/cancel", "OrderController@cancel")->name("api.order.cancel");
    // 服务协议
    Route::get("getAgreementList", "CommonController@agreement")->name("agreement");

    /**
     * 需要登录
     */
    Route::middleware("multiauth:api")->group(function () {
        /**
         * 数据分析
         */
        Route::get("analysis/user_shops", "AnalysisController@user_shops");
        Route::get("analysis/business", "AnalysisController@business");
        Route::get("analysis/business_history", "AnalysisController@business_history");
        Route::get("analysis/shop", "AnalysisController@shop");
        Route::get("analysis/shop_down", "AnalysisController@shop_down");
        Route::get("analysis/platform", "AnalysisController@platform");
        Route::get("analysis/running", "AnalysisController@running");
        /**
         * 子账号管理
         */
        // 子账号-门店列表
        Route::get("sub_account/list", "SubAccountController@index");
        // 子账号添加
        Route::post("sub_account/add", "SubAccountController@store");
        // 子账号删除
        Route::post("sub_account/delete", "SubAccountController@destroy");
        /**
         * 药品管理
         */
        // 同步任务列表
        Route::post("medicine/sync/log", "MedicineSyncLogController@index");
        // 同步任务列表-导出
        Route::get("medicine/sync/log/export", "MedicineSyncLogController@export");
        /**
         * 药品管理
         */
        // 药品门店列表
        Route::get("medicine/shops", "MedicineController@shops");
        // 删除药品
        Route::post("medicine/destroy", "MedicineController@destroy");
        Route::post("medicine/destroy2", "MedicineController@destroy2");
        // 删除同步
        Route::post("medicine/delete-medicine-log", "MedicineController@clearSyncMedicineLog");
        // 药品门店分类
        Route::get("medicine/categories", "MedicineCategoryController@index");
        // 同步品库分类到中台
        Route::get("medicine/category/sync", "MedicineCategoryController@sync");
        // 药品门店商品列表
        Route::get("medicine/product", "MedicineController@product");
        // 根据门店ID和条形码获取药品信息
        Route::get("medicine/product/by/upc", "MedicineController@infoByUpc");
        // 新增商品-批量导入
        Route::post("medicine/import", "MedicineController@import");
        // 修改商品-批量导入
        Route::post("medicine/update/import", "MedicineController@updateImport");
        // 药品管理-同步
        Route::post("medicine/takeout/sync", "MedicineController@sync");
        Route::post("medicine/takeout/sync/log", "MedicineController@sync_log");
        // 药品管理-批量上下架
        Route::post("medicine/takeout/medicineUpperAndLower", "MedicineController@medicineUpperAndLower");
        // 导出
        Route::get("medicine/export", "MedicineController@export_medicine");
        // 导出
        Route::put("medicine/{medicine}", "MedicineController@update");
        // 清空商品
        Route::post("medicine/clear", "MedicineController@clear");
        Route::post("medicine/clear_middle", "MedicineController@clear_middle");
        // 药品状态统计
        Route::get("medicine/statistics/status", "MedicineController@statistics_status");
        // 品库添加商品
        Route::get("medicine/depot/product", "MedicineController@depot_index");
        // 从品库新增商品
        Route::post("medicine/depot/add", "MedicineController@depot_add");
        // 批量更改毛利率
        Route::post("medicine/gpm/update", "MedicineController@batchUpdateGpm");
        // 获取ERP同步开关状态
        Route::get("medicine/erp/status", "MedicineController@erpStatus");
        // 更改ERP同步开关状态
        Route::post("medicine/erp/status", "MedicineController@erpChangeStatus");
        /**
         * WebMI
         */
        Route::get("web/mi/mt/chain", "WebIMController@mt_chain");
        Route::get("web/mi/mt/shop", "WebIMController@mt_shop");

        /**
         * 外卖商品管理
         */
        Route::get("takeout/product", "TakeoutProductController@index");
        Route::put("takeout/product/cost", "TakeoutProductController@update_cost");
        Route::put("takeout/product/name", "TakeoutProductController@update_name");
        Route::put("takeout/product", "TakeoutProductController@update");
        Route::post("takeout/product", "TakeoutProductController@store");
        Route::post("takeout/product/ele", "TakeoutProductController@store_ele");
        Route::post("takeout/product/transfer", "TakeoutProductController@transfer");
        Route::get("takeout/product/log", "TakeoutProductController@log_index");
        Route::get("takeout/product/log/export", "TakeoutProductController@export_logs");
        Route::get("takeout/category", "TakeoutProductController@category_index");
        // VIP商品-导入
        Route::post('takeout/product/import', 'TakeoutProductController@import');
        // VIP商品-导出
        Route::get('takeout/product/export', 'TakeoutProductController@export');

        /**
         * 运力管理
         */
        Route::post("shipper", "ShipperController@add");
        Route::post("shipper/delete", "ShipperController@delete");
        Route::post("shipper/dada/auth", "ShipperController@get_dada_auth_url");
        /**
         * 移动端
         */
        // Route::get("m/order", "OrderController@index");
        /**
         * VIP
         */
        // VIP订单
        Route::get("vip_order/dashboard", "VipOrderController@dashboard");
        Route::get("vip_order/statistic", "VipOrderController@statistic");
        Route::resource('vip_order', 'VipOrderController', ["only" => ["index","show"]]);
        // VIP门店
        Route::get("vip_shop/all", "VipShopController@all");
        Route::resource('vip_shop', 'VipShopController', ["only" => ["index"]]);
        // VIP商品
        Route::resource('vip_product', 'VipProductController', ["only" => ["index"]]);
        // 账单
        Route::resource('vip/bill', 'VipBillController', ["only" => ["index","show"]]);
        /**
         * 【快递订单】
         */
        // 【快递门店列表】
        Route::get('express_order/shops', 'ExpressOrderController@shops');
        // 【快递价格计算】
        Route::get('express_order/pre_order', 'ExpressOrderController@pre_order');
        // 【资源路由】
        Route::resource('express_order', 'ExpressOrderController')->only('index','show','store','destroy');
        /**
         * 城市经理收益
         */
        Route::resource("manager_profit", "ManagerProfitController")->only(["index"])->names("manager_profit");

        /**
         * 合同管理
         */
        Route::prefix("/contract")->group(function () {
            // 可签署合同列表
            Route::post("", "ContractController@index");
            // Route::post("auth", "ContractController@auth");
            // 门店认证
            Route::post("shop_auth", "ContractController@shopAuth");
            // 门店签署合同
            Route::get("shop_sign", "ContractController@shopSign");
            // 用户可签署合同
            Route::get("shops", "ContractController@shops");
        });

        /**
         * 门店管理
         */
        Route::prefix('shop')->group(function () {
            Route::get('auth/meituankaifang', 'ShopController@shop_auth_meituan_canyin')->name('shop.shop_auth_meituan_canyin');
        });


        // *修改密码验证码
        Route::post("auth/code", "AuthController@sms_password")->name("sms_password");
        // 退出
        Route::post("auth/logout", "AuthController@logout");
        // 个人中心-用户信息
        Route::get("user/info", "AuthController@user");
        // 个人中心-用户信息-ant框架返回
        Route::get("user/me", "AuthController@me");
        // 个人中心-用户信息-修改
        Route::put("user/me", "AuthController@update");
        // 个人中心-用户信息-声音状态修改
        Route::post("user/update_voice_status", "AuthController@update_voice_status");
        // 个人中心-用户余额明细
        Route::get("user/balance", "UserMoneyBalanceController@index");
        // 个人中心-用户冻结余额明细
        Route::get("user/frozen/balance", "UserFrozenBalanceController@index");
        // 个人中心-用户运营余额明细
        Route::get("user/operate/balance", "UserOperateBalanceController@index");
        // 首页-合同信息（认证状态、签署信息）
        // Route::get("user/contract", "AuthController@contractInfo");
        // 首页-合同信息（认证状态、签署信息）
        // Route::get("user/contract/sign", "ContractController@userSign");
        // 门店所有跑腿平台状态
        Route::get("{shop}/platform", "ShopController@platform")->name("api.shop.platform");
        // 用户全部可发单药店
        Route::get("shop/all", "ShopController@all")->name("api.shop.all");
        // 订单状态
        Route::get("order/status/{order}", "OrderController@checkStatus");
        // 修改密码
        Route::post("user/reset_password", "AuthController@resetPassword");
        // 修改密码
        Route::post("user/reset_password_by_old", "AuthController@resetPasswordByOld");
        // 统计页面
        Route::get("statistics", "StatisticsController@index");
        // 统计导出-统计
        Route::get("statistics/export", "StatisticsController@export");
        // 统计导出-统计-明细
        // Route::get("statistics/export/detail", "StatisticsController@detail");
        // 分类 2021-02-23 新分类
        Route::get("meiquan/category", "CategoryController@index");
        // 前台-质量公告
        Route::resource("supplier/notice", "SupplierNoticeController", ["only" => ["show", "index"]]);
        // 【H5】轮播图
        Route::get("banner", "BannerController@index");
        // 【H5】广告
        Route::get("ad", "AdController@index");
        // 【H5】通知
        Route::get("notice", "NoticeController@index");
        // 【H5】热门搜索
        Route::get("search_key", "SearchKeyController@index");
        // 【H5】首页搜索
        Route::get("search_key_index", "SearchKeyIndexController@index");
        // 【H5-跑腿】用户可发单门店列表
        Route::get("/h5/shop_success_all", "ShopController@runningAuthAll");
        // 【H5-跑腿】今日订单数量
        Route::get("/h5/order/today_count", "OrderController@todayCount");
        // 门店-城市经理列表
        Route::get("/city_manager", "CityManagerController@index");
        // 中台首页-卡片数据
        Route::get("/index/card", "IndexController@card");
        // 中台首页-电子合同剩余数量
        Route::get("/index/contract", "IndexController@contract");
        // 中台首页-订单数量统计
        Route::get("/index/order", "IndexController@order");
        // 中台首页-城市经理-跑腿门店
        Route::get("/index/manager/running", "IndexController@city_manager_running");
        // 中台首页-城市经理-跑腿门店
        Route::get("/index/manager/online", "IndexController@city_manager_online");

        /**
         * App 订单接口
         */
        // 小程序订单列表
        Route::get("order/app/index/status", "OrderAppController@index_status")->name("order.app.index.status");
        // 小程序订单各个状态数量
        Route::get("order/app/index/statistics", "OrderAppController@index_statistics")->name("order.app.index.statistics");
        // 小程序订单各个状态数量
        Route::get("order/app/{order}", "OrderAppController@show")->name("order.app.show");
        // 忽略订单配送
        Route::post("order/app/ignore/{order}", "OrderAppController@ignore")->name("order.app.ignore");
        // 预发送订单
        Route::get("order/app/advance/{order}", "OrderAppController@advance")->name("order.app.advance");
        // 重置订单
        Route::post("order/app/reset/{order}", "OrderAppController@reset")->name("order.app.reset");
        // 订单派送
        Route::post("order/app/send", "OrderAppController@send")->name("order.app.send");
        /**
         * 订管管理
         */
        // 订单
        Route::post("order/send/{order}", "OrderController@send")->name("order.send");
        // 订单
        // Route::post("picture/ticket", "PictureController@ticket")->name("picture.ticket");
        // 更改交通工具
        Route::post("order/tool/{order}", "OrderController@tool")->name("order.tool");
        // 重新发送订单
        Route::post("order/resend", "OrderController@resend")->name("order.resend");
        // 物品已送回
        Route::post("order/returned", "OrderController@returned")->name("order.returned");
        // Route::post("order2", "OrderController@store2")->name("order.store2");
        // 取消订单
        Route::delete("order/cancel2/{order}", "OrderController@cancel2")->name("api.order.cancel2");
        // 通过订单获取门店详情（配送平台）
        Route::get("/order/getShopInfoByOrder", "OrderController@getShopInfoByOrder")->name("api.order.getShopInfoByOrder");
        Route::get("/order/getShopInfoByOrderPrice", "OrderController@getShopInfoByOrderPrice")->name("api.order.getShopInfoByOrderPrice");
        Route::resource("order", "OrderController", ["only" => ["store", "show", "index", "destroy"]]);
        // 获取配送费
        Route::get("order/money/{shop}", "OrderController@money");

        /**
         * 发单设置
         */
        Route::get("order_setting", "OrderSettingController@show")->name("order_setting.show");
        Route::post("order_setting", "OrderSettingController@store")->name("order_setting.store");
        Route::post("order_setting/reset", "OrderSettingController@reset")->name("order_setting.reset");
        Route::get("order_setting/shops", "OrderSettingController@shops")->name("order_setting.shops");
        Route::get("order_setting/warehouse_shops", "OrderSettingController@warehouse_shops")->name("order_setting.warehouse_shops");

        /**
         * 门店管理-操作外面门店
         */
        // 外卖建店-保存
        Route::post("takeout/shop/shipping", "TakeoutShopController@update_shipping");
        Route::post("takeout/meituan/shop/status", "TakeoutShopController@update_meituan_status");
        Route::post("takeout/ele/shop/status", "TakeoutShopController@update_ele_status");

        /**
         * 门店管理
         */
        // 外卖建店-详情
        Route::get("shop/create", "ShopCreateController@info")->name("shop.create.info");
        // 外卖建店-保存
        Route::post("shop/create", "ShopCreateController@save")->name("shop.create.save");
        // *门店列表-修改三方门店ID
        Route::post("shop/update/three", "ShopController@update_three_id")->name("shop.update.three.id");
        // *门店地址加配送范围信息
        Route::get("shop/range/{shop}", "ShopController@range")->name("shop.range");
        // 根据门店ID获取门店地址加配送范围信息
        Route::get("rangeByShopId", "ShopController@rangeByShopId")->name("shop.rangeByShopId");
        // *绑定门店-自动发单
        Route::post("/shop/binding", "ShopController@binding")->name("shop.binding");
        // *绑定门店-外卖订单
        Route::post("/shop/binding/takeout", "ShopController@bindingTakeout")->name("shop.binding.takeout");
        // *绑定门店-处方订单
        Route::post("/shop/binding/chufang", "ShopController@bindingChufang")->name("shop.binding.chufang");
        // *关闭自动发单-20220414-准备删除
        Route::post("/shop/closeAuto", "ShopController@closeAuto")->name("shop.closeAuto");
        // *关闭自动发单
        Route::post("/shop/auto/close", "ShopController@closeAuto")->name("shop.auto.close");
        // *开启自动发单
        Route::post("/shop/auto/open", "ShopController@openAuto")->name("shop.auto.open");
        // *开启自动发单
        Route::post("/shop/setting", "ShopController@setting")->name("shop.setting");
        // *资源路由-门店
        Route::resource("shop", "ShopController", ["only" => ["store", "show", "index","update"]]);

        /**
         * 商城门店认证
         */
        // *所属用户门店-根据认证结果筛选
        Route::get("shopping", "ShoppingController@index");
        // *提交门店认证
        Route::post("shopping", "ShoppingController@store");
        // *修改门店认证
        Route::put("shopping", "ShoppingController@update");
        // *提交认证门店列表-多状态
        Route::get("shopping/list", "ShoppingController@shopAuthList");
        // *提交认证门店详情
        Route::get("shopping/info", "ShoppingController@show");
        // 认证成功门店列表-待优化（根据状态查询）
        Route::get("shop_auth_success_list", "ShoppingController@shopAuthSuccessList");
        // *已认证门店修改-详情
        Route::get("shopping/change/info", "ShoppingChangeController@show");
        // *已认证门店修改-提交修改
        Route::post("shopping/change", "ShoppingChangeController@store");

        /**
         * 外卖资料上线
         */
        Route::prefix("/online")->group(function () {
            // *上线门店列表
            Route::get("material", "OnlineController@material");
            // *保存上线门店
            Route::post("shop", "OnlineController@store");
            // *更新上线门店
            Route::put("shop", "OnlineController@update");
            // *上线门店列表
            Route::get("shop", "OnlineController@index");
            // *上线门店详情
            Route::get("shop/info", "OnlineController@show");
        });

        /**
         * 外卖管理
         */
        // *已开通处方单门店列表
        Route::get("prescription/shops", "PrescriptionController@shops");
        Route::resource('pharmacist', 'PharmacistController')->only('store', 'destroy');
        // *处方单列表-导出
        Route::get("prescription/export", "PrescriptionController@export");
        // *处方单列表-图片下载
        Route::get("prescription/picture/down", "PrescriptionController@pictureDown");
        // *处方单列表
        Route::get("prescription", "PrescriptionController@index");
        // *处方-线下下单
        Route::get("prescription/down", "PrescriptionController@down");
        // *处方-处方图片压缩包下载列表
        Route::get("prescription/zip", "PrescriptionController@zip");
        // *处方单列表统计
        Route::get("prescription/statistics", "PrescriptionController@statistics");
        // *外卖订单列表
        Route::get("takeout", "WmOrderController@index");
        // *外卖订单-详情
        Route::get("takeout/info", "WmOrderController@show");
        // *外卖订单-详情
        Route::get("takeout/getRpPicture", "WmOrderController@getRpPicture");
        // *外卖订单-打印
        Route::get("takeout/print", "WmOrderController@print_order");
        // *外卖订单-自动打印开关
        Route::get("takeout/print_auto_switch", "WmOrderController@print_auto_switch");
        // *外卖订单-自动打印获取订单
        Route::get("takeout/printer_one", "WmOrderController@printer_one");
        // *外卖订单-打印信息获取
        Route::get("takeout/print_info", "WmOrderController@print_info");
        // *外卖订单-打印机-列表
        Route::get("takeout/print/list", "WmOrderController@print_list");
        // *外卖订单-打印机-添加
        Route::post("takeout/print/add", "WmOrderController@print_add");
        // *外卖订单-打印机-添加
        Route::post("takeout/print/update", "WmOrderController@print_update");
        // *外卖订单-打印机-删除
        Route::post("takeout/print/del", "WmOrderController@print_del");
        // *外卖订单-打印机-清除待打印订单
        Route::post("takeout/print/clear", "WmOrderController@print_clear");
        // *外卖订单-可绑定门店
        Route::get("takeout/print/shops", "WmOrderController@print_shops");

        /**
         * 管理员操作
         */
        Route::middleware(["role:super_man|admin|finance|city_manager|marketing"])->prefix("admin")->namespace("Admin")->group(function () {
            /**
             * 商城品库管理
             */
            Route::get("supplier/depot/index", "SupplierDepotController@index")->name("admin.supplier.depot.index");
            Route::get("supplier/depot/info", "SupplierDepotController@info")->name("admin.supplier.depot.info");
            Route::post("supplier/depot/update", "SupplierDepotController@update")->name("admin.supplier.depot.update");
            /**
             * 品库管理
             */
            Route::get("depot/medicine/categories", "DepotMedicineCategoryController@index")->name("depot.medicine.categories.index");
            Route::get("depot/medicine/category/list_one", "DepotMedicineCategoryController@list_one")->name("depot.medicine.category.list_one");
            Route::get("depot/medicine/category/cascader", "DepotMedicineCategoryController@cascader")->name("depot.medicine.category.cascader");
            Route::post("depot/medicine/category/update", "DepotMedicineCategoryController@update")->name("depot.medicine.category.update");
            Route::post("depot/medicine/category/delete", "DepotMedicineCategoryController@delete")->name("depot.medicine.category.delete");
            Route::post("depot/medicine/product/delete", "DepotMedicineController@delete")->name("depot.medicine.product.delete");
            Route::post("depot/medicine/product/update", "DepotMedicineController@update")->name("depot.medicine.product.update");
            Route::post("depot/medicine/product/update_category", "DepotMedicineController@update_category")->name("depot.medicine.product.update_category");
            Route::get("depot/medicine/product", "DepotMedicineController@index")->name("depot.medicine.product.index");
            /**
             * 审核管理
             */
            // 外卖门店审核
            Route::get("examine/shop/create", "ExamineShopCreateController@index")->name("examine.shop.create.index");
            Route::post("examine/shop/create", "ExamineShopCreateController@update")->name("examine.shop.create.update");
            Route::post("examine/shop/adopt", "ExamineShopCreateController@adopt")->name("examine.shop.create.adopt");
            /**
             * 跑腿设置
             */
            Route::get('shop/setting', 'ShopSettingController@show');
            Route::post("shop/setting", "ShopSettingController@update");
            /**
             * VIP
             */
            // VIP门店
            Route::get("vip_shop/all", "VipShopController@all");
            Route::post("vip_shop/delete", "VipShopController@destroy");
            Route::resource('vip_shop', 'VipShopController', ["only" => ["index","store"]]);
            // VIP商品异常
            Route::get("vip_product/exception/statistics", "VipProductExceptionController@statistics");
            Route::post("vip_product/exception/ignore", "VipProductExceptionController@ignore");
            Route::resource('vip_product/exception', 'VipProductExceptionController', ["only" => ["index","update"]]);
            // VIP商品-ERP-所有
            Route::post('vip/product/erp/cost/all', 'VipProductErpController@erp_cost_all');
            // VIP商品-ERP
            Route::post('vip/product/erp/cost/{vip_product}', 'VipProductErpController@erp_cost');
            // VIP商品-导入
            Route::post('vip_product/import', 'VipProductController@import');
            // VIP商品-导出
            Route::get('vip_product/export', 'VipProductController@export');
            // VIP商品-资源路由
            Route::resource('vip_product', 'VipProductController', ["only" => ["index","store","update"]]);
            // VIP账单
            Route::get('vip/bill/reset/{bill}', 'VipBillController@reset');
            Route::get('vip/bill/export/order', 'VipBillController@export_bill_order');
            Route::get('vip/bill/export/order/shop', 'VipBillController@export_shop_bill_order');
            Route::resource('vip/bill', 'VipBillController', ["only" => ["index","show"]]);
            // VIP订单
            Route::get('vip_order/export_order', 'VipOrderController@export_order');
            Route::get('vip_order/export_product', 'VipOrderController@export_product');
            Route::get('vip_order/statistics', 'VipStatisticsController@orderStatistics');
            Route::get('vip_order/statistics/shop', 'VipStatisticsController@shopStatistics');
            Route::get('vip_order/statistics/manager', 'VipStatisticsController@managerStatistics');
            Route::get('vip_order/statistics/operate', 'VipStatisticsController@operateStatistics');
            Route::get('vip_order/statistics/internal', 'VipStatisticsController@internalStatistics');
            Route::resource('vip_order', 'VipOrderController', ["only" => ["index","show"]]);
            /**
             * 【外卖订单管理】
             */
            // *外卖订单-列表
            Route::get("takeout", "WmOrderController@index");
            // *外卖订单-详情
            Route::get("takeout/info", "WmOrderController@show");
            /**
             * 协议管理
             */
            // 资源路由
            Route::resource("agreement", "AgreementController", ["only" => ["index","show","store","update","destroy"]]);
            /**
             * 统计
             */
            // 首页-跑腿统计
            Route::get("statistic/running", "RunningStatisticController@index")->name("admin.running.statistic.index");
            // 首页-商城统计
            Route::get("statistic/shopping", "ShoppingStatisticController@index")->name("admin.shopping.statistic.index");
            // 首页-外卖资料统计
            Route::get("statistic/online", "OnlineStatisticController@index")->name("admin.online.statistic.index");
            /**
             * 后台外卖管理
             */
            Route::resource("express", "ExpressOrderController", ["only" => ["index"]]);
            /**
             * 后台处方管理
             */
            // *处方单列表-重新结算
            Route::get("prescription/again", "PrescriptionController@again");
            // *处方单列表-删除订单（单个、门店）
            Route::post("prescription/delete", "PrescriptionController@delete");
            // 处方后台-门店管理-门店列表
            Route::get("prescription/shop", "PrescriptionController@shop")->name("admin.prescription.shop.index");
            // 处方后台-门店管理-门店列表-所有
            Route::get("prescription/shop/all", "PrescriptionController@shop_all")->name("admin.prescription.shop.all");
            // 处方后台-门店管理-门店列表-导出
            Route::get("prescription/shop/export", "PrescriptionController@shop_export")->name("admin.prescription.shop.export");
            // 处方后台-门店管理-更新门店状态
            Route::post("prescription/shop", "PrescriptionController@shop_update")->name("admin.prescription.shop.update");
            // 处方后台-门店管理-关闭处方
            Route::post("prescription/shop/delete", "PrescriptionController@shop_delete")->name("admin.prescription.shop.delete");
            // 处方后台-门店管理-设置处方费用
            Route::post("prescription/shop/cost", "PrescriptionController@shop_cost")->name("admin.prescription.shop.cost");
            // 处方后台-门店管理-门店统计
            Route::get("prescription/shop/statistics", "PrescriptionController@shop_statistics")->name("admin.prescription.shop.statistics");
            // *处方单列表-导出
            Route::get("prescription/export", "PrescriptionController@export");
            // 处方后台-处方管理-订单列表
            Route::get("prescription", "PrescriptionController@index")->name("admin.prescription.index");
            // 处方后台-处方管理-订单统计
            Route::get("prescription/statistics", "PrescriptionController@statistics")->name("admin.prescription.statistics");
            // 处方后台-处方管理-导入列表
            Route::get("prescription/import", "WmPrescriptionImportController@index")->name("admin.prescription.import.index");
            // 处方后台-处方管理-导入
            Route::post("prescription/import", "WmPrescriptionImportController@store")->name("admin.prescription.import.store");
            /**
             * 门店后台管理
             */
            // 门店迁移
            Route::post("shop/transfer", "ShopController@transfer")->name("admin.shop.transfer");
            // 门店导出
            Route::post("shop/manager/update", "ShopController@manager_update")->name("admin.shop.manager.update");
            // 门店导出
            Route::get("shop/export", "ShopController@export")->name("admin.shop.export");
            // 门店管理-更新门店三方ID
            Route::post("shop/update/three", "ShopController@update_three")->name("admin.shop.update_three");
            // 审核管理-三方门店ID审核
            Route::get("shop/example/three_id", "ShopController@apply_three_id_shops")->name("admin.shop.example.three_id");
            // 审核管理-三方门店ID审核
            Route::post("shop/example/three_id", "ShopController@apply_three_id_save")->name("admin.shop.example.three_id.save");
            // **门店管理-全部门店列表
            Route::get("shop/all", "ShopController@all");
            // **门店管理-ERP状态切换
            Route::post("shop/erp/status", "ShopController@erpStatus");
            // **门店管理-保存仓库设置
            Route::post("shop/warehouse", "ShopController@warehouse");
            // 修改跑腿订单加价
            Route::post("shop/running/money/add", "ShopController@moneyAdd")->name("admin.shop.running.money.add");
            // **门店管理-门店列表
            Route::resource("shop", "ShopController", ["only" => ["index"]]);
            /**
             * 城市经理
             */
            // 门店导出
            Route::resource("manager", "ManagerController", ["only" => ["index"]]);
            /**
             * 商城后台管理
             */
            // 商城轮播图
            Route::resource("banner", "BannerController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 商城分类
            Route::resource("category", "CategoryController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 首页关键词
            Route::resource("searchKeyIndex", "SearchKeyIndexController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 搜索关键词
            Route::resource("searchKey", "SearchKeyController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 轮播公告
            Route::resource("notice", "NoticeController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 轮播广告
            Route::resource("ad", "AdController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 质量公告
            Route::resource("supplier/notice", "SupplierNoticeController", ["only" => ["index", "show", "store", "update", "destroy"]]);
            // 外卖资料-导出
            Route::get("online_shop/export", "OnlineShopController@export")->name("admin.online_shop.export");
            // 外卖资料
            // Route::resource("online_shop", "OnlineShopController", ["only" => ["index", "show"]]);
            Route::get("online_shop/info", "OnlineShopController@info_by_shop_id")->name("admin.online_shop.info.info_by_shop_id");
            Route::put("online_shop/info", "OnlineShopController@update_by_shop_id")->name("admin.online_shop.info.update_by_shop_id");
            // 用户管理-充值记录
            Route::get("deposit", "DepositController@index")->name("admin.deposit.index");
            // 用户管理-充值记录-导出
            Route::get("deposit/export", "DepositController@export")->name("admin.deposit.export");

            // 跑腿订单结算
            // 财务管理-跑腿订单结算
            Route::get("/running/fundrecord", "RunningFundrecordController@index");
            // 财务管理-跑腿订单结算
            Route::get("/running/fundrecord/export", "RunningFundrecordController@export");

            /**
             * 用户
             */
            // IM用户-列表
            Route::get("/user/im", "UserController@im_index");
            // IM用户-更新
            Route::post("/user/im/update", "UserController@im_update");
            // IM用户-删除
            Route::post("/user/im/delete", "UserController@im_delete");
            // 用户管理-所有用户余额统计
            Route::get("/user/statistics", "UserController@statistics");
            // 用户管理-用户列表-余额消费明细
            Route::get("/user/balance", "UserController@balance");
            // 用户管理-用户列表-余额消费明细
            Route::get("/user/balance/export", "UserController@balanceExport");
            // 用户管理-管理员-禁用用户
            Route::post("user/disable", "UserController@disable");
            // 用户管理-管理员-设置分佣
            Route::post("user/return", "UserController@returnStore");
            // 用户管理-管理员-获取分佣
            Route::get("user/return", "UserController@returnShow");
            // 用户管理-管理员-清空用户跑腿余额
            Route::post("user/money/clear", "UserController@money_clear");
            // 用户管理-管理员-运营经理
            Route::post("user/operate", "UserController@operate_update");
            Route::get("user/operate", "UserController@operate_index");
            // 用户管理-管理员-内勤经理
            Route::post("user/internal", "UserController@internal_update");
            Route::get("user/internal", "UserController@internal_index");
            // 用户管理-管理员创建用户
            Route::post("/user", "UserController@store");
            // 用户管理-管理员修改用户
            Route::put("/user", "UserController@update");
            // 用户管理-城市经理-负责城市列表
            Route::get("/user/manager/city", "ManagerCityController@index");
            // 用户管理-城市经理-添加城市
            Route::post("/user/manager/city", "ManagerCityController@store");
            // 用户管理-城市经理-删除城市
            Route::delete("/user/manager/city", "ManagerCityController@destroy");
            // 城市经理-资源路由
            Route::resource("city_manager", "CityManagerController", ["only" => ["store", "show", "index", "update", "destroy"]]);

            /**
             * 财务管理
             */
            Route::prefix("finance")->group(function () {
                // 平台资金
                Route::get("shopping/store", "FundController@shops");
                Route::get("supplier/store", "FundController@supplier");
                Route::get("running_orders", "FundController@running_orders");
                Route::get("shopping_orders", "FundController@shopping_orders");
                Route::get("statistic", "FundController@statistic");
                // 经理收益
                Route::get("manager/profit", "ManagerProfitController@index");
            });
        });
        Route::middleware(["role:super_man|admin|finance|city_manager|marketing"])->group(function () {
            // 用户管理
            Route::post("admin/user/chain", "UserController@chain");
            // 用户管理-导出
            Route::get("/user/export", "UserController@export");

            // *商城认证-审核列表
            Route::get("examine/shopping", "ExamineShoppingController@index");
            // *商城认证-审核
            Route::post("examine/shopping", "ExamineShoppingController@store");
            // *商城认证-资料修改申请-审核列表
            Route::get("examine/shopping/change", "ExamineShoppingController@changeIndex");
            // *商城认证-资料修改申请-审核列表
            Route::post("examine/shopping/change", "ExamineShoppingController@changeStore");

            // *审核接口-管理员-审核列表
            Route::get("examine/online", "OnlineController@examineList");
            // *审核接口-管理员-详情
            Route::get("examine/online/show", "OnlineController@examineShow");
            // *审核接口-管理员-审核
            Route::post("examine/online", "OnlineController@examine");

            // *跑腿审核-门店列表
            Route::get("examine/shop", "ExamineShopController@index");
            // *跑腿审核-审核操作
            Route::post("examine/shop", "ExamineShopController@store");
            // *跑腿审核-更改门店名称
            Route::post("examine/shop/update", "ExamineShopController@update");

            // *自动接单-门店列表
            Route::get("examine/auto", "ExamineShopController@autoList");
            // *自动接单-审核操作
            Route::post("examine/auto", "ExamineShopController@AutoStore");

            // *处方订单-门店列表
            Route::get("examine/auto/prescription", "ExamineShopController@prescriptionList");
            // *处方订单-审核操作
            Route::post("examine/auto/prescription", "ExamineShopController@prescriptionStore");

            // *外卖平台-门店列表
            Route::get("examine/platform/shops", "Admin\ShopPlatFormController@index");
            // *外卖平台-设置平台
            Route::post("examine/platform/shops", "Admin\ShopPlatFormController@update");

            // 商城后台
            // *商城后台-商品列表
            Route::get("/shopAdmin/product", "ShopAdminController@productList");
            // *商城后台-商品排序
            Route::post("/shopAdmin/product/sort", "ShopAdminController@productSort");
            // *商城后台-商品活动设置
            Route::post("/shopAdmin/product/active", "ShopAdminController@productActive");
            // *商城后台-订单列表
            Route::get("/shopAdmin/order", "ShopAdminController@orderList");
            // *商城后台-订单列表-导出
            Route::get("/shopAdmin/order/export", "ShopAdminController@export");
            // *商城后台-取消订单
            Route::post("/shopAdmin/order/cancel", "ShopAdminController@cancelOrder");
            // *商城后台-订单收货
            Route::post("/shopAdmin/order/receive", "ShopAdminController@receiveOrder");
            // *商城后台-重置结算信息
            Route::post("/shopAdmin/order/reset", "ShopAdminController@resetOrder");
            // *商城后台-操作收货
            // Route::post("/shopAdmin/order/cancel", "ShopAdminController@receiveOrder");
            // *商城后台-供货商列表
            Route::get("/shopAdmin/supplier", "ShopAdminController@supplierList");
            // *商城后台-供货商列表-上下架
            Route::post("/shopAdmin/supplier/online", "ShopAdminController@supplierOnline");
            // *商城后台-供货商开发票列表
            Route::get("/shopAdmin/supplier/invoice", "ShopAdminController@supplierInvoiceList");
            // *商城后台-供货商开发票-已开
            Route::post("/shopAdmin/supplier/invoice", "ShopAdminController@supplierInvoice");
            // *商城后台-供货商开发票列表
            Route::get("/shopAdmin/supplier/withdrawal", "ShopAdminController@supplierWithdrawalList");
            // *商城后台-供货商开发票-已开
            Route::post("/shopAdmin/supplier/withdrawal", "ShopAdminController@supplierWithdrawal");

            // ERP管理
            // ERP管理-key列表
            Route::get("/erpAdmin/access_key/info", "ErpAdminAccessKeyController@info");
            // ERP管理-key列表
            Route::get("/erpAdmin/access_key", "ErpAdminAccessKeyController@index");
            // ERP管理-key添加
            Route::post("/erpAdmin/access_key", "ErpAdminAccessKeyController@store");
            // ERP管理-key修改
            Route::put("/erpAdmin/access_key", "ErpAdminAccessKeyController@update");
            // ERP管理-key删除
            Route::delete("/erpAdmin/access_key", "ErpAdminAccessKeyController@destroy");
            // ERP管理-key门店列表
            Route::get("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@index");
            // ERP管理-key门店添加
            Route::post("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@store");
            // ERP管理-key门店修改
            Route::put("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@update");
            // ERP管理-key门店删除
            Route::delete("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@destroy");
            // ERP管理-品库列表
            Route::get("/erpAdmin/depot", "ErpDepotController@index");
            // ERP管理-品库添加
            Route::post("/erpAdmin/depot", "ErpDepotController@store");
            // ERP管理-品库修改
            Route::put("/erpAdmin/depot", "ErpDepotController@update");
            // ERP管理-品库分类
            Route::get("/erpAdmin/depot/category", "ErpDepotController@category");

        });

        /**
         * 管理员操作
         */
        Route::get("order/location/{order}", "OrderController@location");
        // 未绑定全部药店
        Route::get("shop/wei", "ShopController@wei")->name("api.shop.wei")->middleware("role:super_man");
        // 同步门店
        Route::post("shop/sync", "ShopController@sync")->name("api.shop.sync")->middleware("role:super_man");
        // 管理员手动充值
        Route::post("user/recharge", "UserController@recharge")->name("api.shop.recharge")->middleware("role:super_man");
        // 管理员查看充值列表
        Route::get("user/recharge", "UserController@rechargeList")->middleware("role:super_man");
        // 用户
        Route::resource("user", "UserController", ["only" => ["store", "show", "index", "update"]])->middleware("role:super_man");

        /**
         * 资源路由
         */
        // 创建待审核门店
        Route::post("storeShop", "ShopController@storeShop")->name("shop.storeShop");
        // 审核门店
        Route::post("/shop/examine/{shop}", "ShopController@examine")->name("shop.examine");
        // 删除门店
        Route::post("/shop/delete/{shop}", "ShopController@delete")->name("shop.delete");
        // 可以看到的所有门店
        Route::get("shopAll", "ShopController@shopAll")->name("shop.shopAll");
        Route::get("shop/list/search", "ShopController@get_shop_search")->name("shop.list.search");
        // 个人中心-商城余额-微信支付-公众号user/balance
        // Route::post("deposit/shop/wechat/mp", "DepositController@shopWechatMp");
        // 个人中心-商城余额-微信支付-扫码
        // Route::post("deposit/shop/wechat/scan", "DepositController@shopWechatScan");
        // 个人中心-商城余额-微信支付-小程序
        Route::post("deposit/shop/wechat/miniapp", "DepositController@shopWechatMiniApp");
        // 个人中心-跑腿余额-微信支付-小程序
        Route::post("deposit/running/wechat/miniapp", "DepositController@runningWechatMiniApp");
        // 个人中心-用户充值
        Route::resource("deposit", "DepositController", ["only" => ["store", "show", "index"]]);
        // 所有权限列表
        Route::get("permission/all", "PermissionController@all")->name("permission.all");
        // 权限管理
        Route::resource("permission", "PermissionController", ["only" => ["store", "index", "update", "destroy"]]);
        // 所有角色列表
        Route::get("role/all", "RoleController@all")->name("role.all");
        // 角色管理
        Route::resource("role", "RoleController", ["only" => ["store", "show", "index", "update"]]);

        /**
         * 采购商城
         */
        Route::prefix("/purchase")->group(function () {
            // 采购购物车
            Route::get("category", "SupplierCategoryController@index");
            // 移动端全部分类
            Route::get("category_all", "SupplierCategoryController@all");
            // 移动端根据二级分类获取一级分类
            Route::get("category_all", "SupplierCategoryController@all");
            // 采购商品列表RedisQueue.php
            Route::get("product", "SupplierProductController@index");
            // 采购活动商品列表
            Route::get("active/product", "SupplierProductController@activeList");
            // 采购商品详情
            Route::get("product/{supplier_product}", "SupplierProductController@show");
            // 供货商信息
            Route::get("shop/info", "SupplierShopController@show");
            // 供货商商品列表
            Route::get("shop/product", "SupplierShopController@productList");
            // 采购购物车列表
            Route::get("cart", "SupplierCartController@index");
            // 采购购物车添加
            Route::post("cart", "SupplierCartController@store");
            // 采购购物车删除
            Route::delete("cart", "SupplierCartController@destroy");
            // 采购购物车-修改数量
            Route::post("cart/change", "SupplierCartController@change");
            // 采购购物车-设置选中
            Route::post("cart/checked", "SupplierCartController@checked");
            // 采购购物车-结算
            Route::get("cart/settlement", "SupplierCartController@settlement");
            // 采购购物车-商品总数量
            Route::get("cart/number", "SupplierCartController@number");
            // 地址
            Route::get("address", "SupplierAddressController@index");
            // 订单
            Route::resource("order", "SupplierOrderController");
            // 支付订单
            Route::post("order/pay", "PaymentController@pay");
            // 商城订单-微信-公众号-支付
            Route::post("pay/order/wechat/mp", "PaymentController@supplierOrderByWeChatMp");
            // 商城订单-微信-扫码-支付
            Route::post("pay/order/wechat/scan", "PaymentController@supplierOrderByWeChatScan");
            // 商城订单-微信-小程序-支付
            Route::post("pay/order/wechat/miniapp", "PaymentController@supplierOrderByWeChatMiniApp");
            // 确认收货
            Route::post("order/receive", "SupplierOrderController@receiveOrder");
            // 物流信息
            Route::get("order/{supplier_order}/logistics", "SupplierOrderController@logistics");
            // 支付订单页面调用-订单详情
            Route::get("payOrders", "SupplierOrderController@payOrders");
            // 收货
            Route::post("received/{supplier_order}", "SupplierOrderController@received")->name("supplier.order.received");
            // 供货商审核列表
            Route::get("supplier/authList", "SupplierUserController@user");
            // 供货商审核
            Route::post("supplier/setAuth", "SupplierUserController@example");
            // 药品审核列表
            Route::get("depot/authList", "ExampleProductController@index");
            // 药品审核
            Route::post("depot/setAuth", "ExampleProductController@setAuth");
            // 商城余额明细
            Route::get("balance", "UserFrozenBalanceController@index");
        });
        // 设置默认收货门店
        Route::post("shop/userShop", "ShopController@setUserShop");
        // 设置默认发单门店
        Route::post("shop/runningShop", "ShopController@setRunningShop");
    });
});

/**
 * 回调接口
 */

/**
 * 支付回调接口
 */
Route::group(["namespace" => "Api"], function () {
    Route::post("payment/wechat/notify", "PaymentController@wechatNotify");
    Route::post("payment/wechat/notify2", "PaymentController@wechatNotify2");
    Route::post("payment/wechat/supplier/refund", "PaymentController@supplierRefund");
    Route::post("payment/wechat/notify_supplier", "PaymentController@wechatSupplierNotify");
    Route::post("payment/alipay/notify", "PaymentController@alipayNotify");
    // 运营充值回调
    Route::post("payment/wechat/notify/operate", "PaymentController@wechatNotifyOperate");
});

/**
 * 美团回调接口
 */
Route::namespace("MeiTuan")->prefix("mt")->group(function () {
    // 门店状态回调
    Route::post("shop/status", "ShopController@status");
    // 订单状态回调
    Route::post("order/status", "OrderController@status");
    // 订单异常回调
    Route::post("order/exception", "OrderController@exception");
});

/**
 * 蜂鸟回调接口
 */
Route::namespace("FengNiao")->prefix("fengniao")->group(function () {
    // 门店状态回调
    Route::post("shop/status", "ShopController@status");
    // 订单状态回调
    Route::post("order/status", "OrderController@status");
    // 授权回调
    Route::any("shop/back_auth", "ShopController@back_auth");
});

/**
 * 闪送回调接口
 */
Route::namespace("ShanSong")->prefix("shansong")->group(function () {
    // 订单状态回调
    Route::post("order/status", "OrderController@status");
});

/**
 * 药柜回调接口
 */
Route::namespace("Api")->prefix("zg")->group(function () {
    // 结算订单
    Route::post("order/settlement", "YaoguiController@settlement");
    // 隐私号降级
    Route::post("order/downgrade", "YaoguiController@downgrade");
    // 创建订单
    Route::post("order/create", "YaoguiController@create");
    // 取消订单
    Route::post("order/cancel", "YaoguiController@cancel");
    // 催单
    Route::post("order/urge", "YaoguiController@urge");
});

/**
 * 顺丰回调接口
 */
Route::namespace("Api")->prefix("shunfeng")->group(function () {
    // 配送状态更改
    Route::post("order/status", "ShunfengController@status");
    // 订单完成
    Route::post("order/complete", "ShunfengController@complete");
    // 顺丰原因取消
    Route::post("order/cancel", "ShunfengController@cancel");
    // 订单配送异常
    Route::post("order/exception", "ShunfengController@exceptionQishou");
    // 骑士撤单
    Route::post("order/cancel_qishou", "ShunfengController@cancelQishou");
    // 授权回调
    Route::post("order/auth", "ShunfengController@auth");
});

/**
 * 美团外卖民康回调接口
 */
Route::namespace("Api")->prefix("waimai")->group(function () {
    // 结算订单
    Route::post("minkang/confirm", "MinKangController@confirm");
});
Route::namespace("Api\Waimai")->prefix("waimai/minkang")->group(function () {
    // 订单配送状态-美配
    Route::post("order/status/mp", "MinKangOrderController@statusMp");
    // 订单配送状态-自配
    Route::post("order/status/zp", "MinKangOrderController@statusZp");
    // 订单完成
    Route::post("order/complete", "MinKangOrderController@complete");
});

/**
 * 蜂鸟测试接口
 */
// Route::group(["namespace" => "Test"], function () {
//     Route::post("/test/fn/createShop", "FengNiaoTestController@createShop");
//     Route::post("/test/fn/updateShop", "FengNiaoTestController@updateShop");
//     Route::post("/test/fn/getShop", "FengNiaoTestController@getShop");
//     Route::post("/test/fn/getArea", "FengNiaoTestController@getArea");
//
//     Route::post("/test/fn/createOrder", "FengNiaoTestController@createOrder");
//     Route::post("/test/fn/cancelOrder", "FengNiaoTestController@cancelOrder");
//     Route::post("/test/fn/getOrder", "FengNiaoTestController@getOrder");
//     Route::post("/test/fn/complaintOrder", "FengNiaoTestController@complaintOrder");
//
//     Route::post("/test/fn/delivery", "FengNiaoTestController@delivery");
//     Route::post("/test/fn/carrier", "FengNiaoTestController@carrier");
//     Route::post("/test/fn/route", "FengNiaoTestController@route");
// });

/**
 * 闪送测试接口
 */
// Route::namespace("Test")->prefix("test/ss")->group(function () {
//     Route::post("createShop", "ShanSongTestController@createShop");
//     Route::post("getShop", "ShanSongTestController@getShop");
//
//     Route::post("orderCalculate", "ShanSongTestController@orderCalculate");
//     Route::post("createOrder", "ShanSongTestController@createOrder");
//     Route::post("cancelOrder", "ShanSongTestController@cancelOrder");
//     Route::post("getOrder", "ShanSongTestController@getOrder");
//     Route::post("complaintOrder", "ShanSongTestController@complaintOrder");
//
//     Route::post("delivery", "ShanSongTestController@delivery");
//     Route::post("carrier", "ShanSongTestController@carrier");
//     Route::post("route", "ShanSongTestController@route");
// });


/**
 * 美团测试接口
 */
Route::namespace("Test")->prefix("test/mt")->group(function () {
    Route::get("shopIdList", "MeiTuanTestController@shopIdList");
});
