<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
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
        ]);
    }
}
