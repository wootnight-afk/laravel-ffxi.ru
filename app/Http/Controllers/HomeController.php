<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\HomePageContent;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(HomePageContent $home): View
    {
        $latestNews = News::query()
            ->site()
            ->published()
            ->with('user')
            ->pinnedFirst()
            ->limit(3)
            ->get();

        return view('home', [
            'latestNews' => $latestNews,
            'home' => $home->view(),
        ]);
    }
}
