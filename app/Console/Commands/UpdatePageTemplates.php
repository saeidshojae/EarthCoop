<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Page;

class UpdatePageTemplates extends Command
{
    protected $signature = 'pages:update-templates';
    protected $description = 'Update page templates based on slug';

    public function handle()
    {
        // Update about page
        $aboutPage = Page::where('slug', 'drbarh-arth-kop')
            ->orWhere('slug', 'about')
            ->orWhere('slug', 'darbareh')
            ->first();
        
        if ($aboutPage) {
            $aboutPage->template = 'about';
            $aboutPage->save();
            $this->info("Updated '{$aboutPage->slug}' to template 'about'");
        }

        // Update help page
        $helpPage = Page::where('slug', 'rahnmay-astfadh')
            ->orWhere('slug', 'help')
            ->orWhere('slug', 'rahnama')
            ->first();
        
        if ($helpPage) {
            $helpPage->template = 'help';
            $helpPage->save();
            $this->info("Updated '{$helpPage->slug}' to template 'help'");
        }

        // Update cooperation page
        $cooperationPage = Page::where('slug', 'frst-hmkary')
            ->orWhere('slug', 'cooperation')
            ->orWhere('slug', 'hamkari')
            ->first();
        
        if ($cooperationPage) {
            $cooperationPage->template = 'cooperation';
            $cooperationPage->save();
            $this->info("Updated '{$cooperationPage->slug}' to template 'cooperation'");
        }

        $faqPage = Page::where('slug', 'faq')
            ->orWhere('slug', 'faq-page')
            ->first();

        if ($faqPage) {
            $faqPage->template = 'faq';
            $faqPage->save();
            $this->info("Updated '{$faqPage->slug}' to template 'faq'");
        }

        $this->info('Done!');
        return 0;
    }
}

