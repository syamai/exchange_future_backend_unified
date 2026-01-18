<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Symfony\Component\HttpFoundation\Response;
// use Redis;
use Illuminate\Support\Facades\Redis;

class PromController extends Controller
{
    private $collectorRegistry;
    public function __construct(CollectorRegistry $collectorRegistry)
    {
        $this->collectorRegistry = $collectorRegistry;
    }

    public function index() {
        $registry = $this->collectorRegistry;
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
    public function metric()
    {
        $registry = new CollectorRegistry(new InMemory());

        $countOrderBookQueue = count(Redis::keys('*orderBook*'));
        $countPriceQueue = count(Redis::keys('*Price*'));
        $countChartQueue = count(Redis::keys('*Chart*'));
        $countMasterdataQueue = count(Redis::keys('*masterdata*'));
        $countDataversionQueue = count(Redis::keys('*dataVersion*'));
        $countTransactionQueue = count(Redis::keys('*Transaction*'));
        $countMailQueue = count(Redis::keys('*mail*'));
        
        $gauge = $registry->registerGauge('spot', 'healthcheck', 'count queue is running', ['name']);
        $gauge->set($countOrderBookQueue >= 1000 ? 0 : 1, ['orderbook']);
        $gauge->set($countPriceQueue >= 300 ? 0 : 1, ['price']);
        $gauge->set($countChartQueue >= 100 ? 0 : 1, ['chart']);
        $gauge->set($countMasterdataQueue >= 10 ? 0 : 1, ['masterdata']);
        $gauge->set($countDataversionQueue > 5 ? 0 : 1, ['dataversion']);
        $gauge->set($countTransactionQueue > 100 ? 0 : 1, ['transaction']);
        $gauge->set($countMailQueue >= 100 ? 0 : 1, ['mail']);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
