<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Console\Command;

class UpdateProductInformations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:update-product-informations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update product informations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $products = Product::get();
        foreach ($products as $product) {
            UpdateProductInformationJob::dispatch($product);
        }
    }
}
