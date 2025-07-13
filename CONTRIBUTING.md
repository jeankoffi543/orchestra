# Add list of autorized artisan or console command to pass throw route
# BUg
    it('should update with wrong juridiction', function ($guestJuridiction, $createdJuridiction<----------------) {
        $guestJuridiction = $guestJuridiction();
        $createdJuridiction = $createdJuridiction();

        $guestJuridiction = $guestJuridiction->toArray();

        $this->put('juridictions/$createdJuridiction->juridiction_id', $guestJuridiction)
            ->assertBadRequest();
    })->with('guest juridiction');<----------------