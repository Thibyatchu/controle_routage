<?php


namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class TreatmentCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    // tests
    public function tryToTest(AcceptanceTester $I)
    {
        $I->amOnPage('/treatment');
    }
}
