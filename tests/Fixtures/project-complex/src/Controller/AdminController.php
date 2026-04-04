<?php

declare(strict_types=1);

namespace App\Controller;

class AdminController
{
    public function redirectToDashboard(): string
    {
        return $this->redirectToRoute('sylius_admin_dashboard_statistics');
    }

    public function searchProducts(): string
    {
        return $this->generateUrl('sylius_admin_ajax_product_by_name_phrase');
    }

    public function findVariants(): string
    {
        return $this->generateUrl('sylius_admin_ajax_all_product_variants_by_codes');
    }

    public function getAttributes(): string
    {
        return $this->generateUrl('sylius_admin_get_product_attributes');
    }
}
