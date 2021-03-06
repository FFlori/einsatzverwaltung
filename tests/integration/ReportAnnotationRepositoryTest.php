<?php
namespace abrain\Einsatzverwaltung;

use abrain\Einsatzverwaltung\Model\ReportAnnotation;

class ReportAnnotationRepositoryTest extends \WP_UnitTestCase
{
    public function testCreateReportAnnotation()
    {
        $repository = ReportAnnotationRepository::getInstance();
        self::assertCount(3, $repository->getAnnotations());
        $repository->addAnnotation(new ReportAnnotation('dings', 'Dings', '', 'camera', 'aktiv', 'inaktiv'));
        self::assertCount(4, $repository->getAnnotations());
        self::assertArrayHasKey('dings', $repository->getAnnotations());
        self::assertContains('dings', $repository->getAnnotationIdentifiers());
    }
}
