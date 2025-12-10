<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\User;
use App\Form\RegisterUserAccount;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegisterUserAccountTest extends TestCase
{
    private RegisterUserAccount $form;

    protected function setUp(): void
    {
        $this->form = new RegisterUserAccount();
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);

        $resolver
            ->expects($this->once())
            ->method('setDefaults')
            ->with([
                'data_class' => User::class,
            ]);

        $this->form->configureOptions($resolver);
    }

    public function testFormHasCorrectName(): void
    {
        $this->assertEquals('App\Form\RegisterUserAccount', get_class($this->form));
    }

    // Note: Full testing of form building requires integration tests
    // with real Symfony form system. These unit tests verify basic configuration.
}
