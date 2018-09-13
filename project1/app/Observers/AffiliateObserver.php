<?php
namespace App\Observers;

use App\Affiliate;
use App\AffiliateEmailTemplate;
use App\EmailTemplate;

/**
 * Affiliate observer
 */
class AffiliateObserver
{
    /**
     * After affiliate has been created
     */
    public function created(Affiliate $aff)
    {
        $templates = EmailTemplate::all();

        foreach ($templates as $template) {
            $affiliateEmailTemplate = new AffiliateEmailTemplate();
            $affiliateEmailTemplate->email_template_id = $template->id;
            $affiliateEmailTemplate->affiliate_id = $aff->id;
            $affiliateEmailTemplate->save();
        }
    }

    /**
     * On affiliate deleting
     */
    public function deleting(Affiliate $aff)
    {
        AffiliateEmailTemplate::where('affiliate_id', '=', $aff->id)->delete();
    }
}
