<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Customization\Crud\Actions;

use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\Crud\Actions\ClosureConfirmationTestCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Kernel;

/**
 * Tests for Action::askConfirmation() with a per-entity \Closure message.
 */
class ClosureConfirmationTest extends AbstractCrudTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function getControllerFqcn(): string
    {
        return ClosureConfirmationTestCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return \EasyCorp\Bundle\EasyAdminBundle\Tests\Functional\Apps\CustomizationApp\Controller\DashboardController::class;
    }

    public function testClosureBuildsThePerEntityConfirmationMessage(): void
    {
        $crawler = $this->client->request('GET', $this->generateIndexUrl());

        static::assertResponseIsSuccessful();

        $rows = $crawler->filter('tr[data-id]');
        static::assertGreaterThan(1, $rows->count());

        $oddSeen = 0;
        $evenSeen = 0;
        foreach ($rows as $row) {
            $rowCrawler = new \Symfony\Component\DomCrawler\Crawler($row);
            $id = (int) $rowCrawler->attr('data-id');
            $deleteAction = $rowCrawler->filter('[data-action-name="delete"]');
            static::assertSame(1, $deleteAction->count());
            static::assertSame('true', $deleteAction->attr('data-action-confirmation'));

            if (0 === $id % 2) {
                // `true` returned by the closure -> generic modal, no custom message
                static::assertNull($deleteAction->attr('data-action-confirmation-message'));
                ++$evenSeen;
            } else {
                $message = (string) $deleteAction->attr('data-action-confirmation-message');
                static::assertStringStartsWith('Deleting "', $message);
                static::assertStringContainsString('cannot be undone', $message);
                ++$oddSeen;
            }
        }

        // both closure branches were exercised
        static::assertGreaterThan(0, $oddSeen);
        static::assertGreaterThan(0, $evenSeen);
    }
}
