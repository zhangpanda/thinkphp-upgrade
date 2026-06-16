<?php

/**
 * ThinkPHP-Upgrade Web UI API Routes
 *
 * 用于 Laravel 11 后端，安装方式：
 *   # see README for install
 *   php artisan vendor:publish --tag=phplift-routes
 *
 * 以下为路由定义骨架，需要在 Laravel 项目中注册。
 */

use Illuminate\Support\Facades\Route;

Route::prefix('api/tp-upgrade')->group(function () {
    // 项目管理
    Route::post('/projects', 'ProjectController@store');       // 导入项目
    Route::get('/projects/{id}', 'ProjectController@show');    // 项目详情

    // 分析
    Route::post('/projects/{id}/analyze', 'AnalysisController@start');   // 启动分析
    Route::get('/projects/{id}/analysis', 'AnalysisController@status');  // 分析状态/结果

    // 转换
    Route::post('/projects/{id}/transform', 'TransformController@start');       // 启动转换
    Route::get('/projects/{id}/transform/status', 'TransformController@status'); // 转换进度
    Route::post('/projects/{id}/transform/confirm', 'TransformController@confirm'); // 确认单个变更
    Route::post('/projects/{id}/transform/skip', 'TransformController@skip');       // 跳过单个变更

    // 报告
    Route::get('/projects/{id}/report', 'ReportController@show');         // JSON 报告
    Route::get('/projects/{id}/report/html', 'ReportController@html');    // HTML 报告下载
});
