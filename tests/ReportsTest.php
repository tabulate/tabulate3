<?php

use \Tabulate\DB\Reports;

class ReportsTest extends TestBase
{

    /**
     * @testdox The Reports system uses two database tables, which are created on activation.
     * @test
     */
    public function activate()
    {
        $reports = $this->db->getTable(Reports::reportsTableName());
        $this->assertEquals('reports', $reports->getName());
        $reportSources = $this->db->getTable(Reports::reportSourcesTableName());
        $this->assertEquals('report_sources', $reportSources->getName());
    }

    /**
     * @testdox On activation, a default report is created that lists all reports. Its ID is 1.
     * @test
     */
    public function defaultReport()
    {
        $reports = new Reports($this->db);
        $default = $reports->get_template(1);
        $this->assertEquals('Reports', $default->title);
        $default_html = "<dl>\n"
                . "  <dt><a href='/reports/1'>Reports</a></dt>\n"
                . "  <dd>List of all Reports.</dd>\n"
                . "</dl>";
        $this->assertStringMatchesFormat($default_html, $default->render());
    }

    /**
     * @testdox A report has, at a minimum, a title and a template. It handled as a normal record in the reports table.
     * @test
     */
    public function template()
    {
        $reportsTable = $this->db->getTable(Reports::reportsTableName());
        $report = $reportsTable->saveRecord(array(
            'title' => 'Test Report',
            'template' => 'Lorem ipsum.',
        ));
        $this->assertEquals('Test Report', $report->title());
        $reports = new Reports($this->db);
        $template = $reports->get_template($report->id());
        $this->assertEquals('Test Report', $report->title());
        $this->assertInstanceOf('\Tabulate\Template', $template);
        $this->assertEquals('Lorem ipsum.', $template->render());
    }

    /**
     * @testdox Reports can have source queries injected into them.
     * @test
     */
    public function sources()
    {
        $reportsTable = $this->db->getTable(Reports::reportsTableName());
        $report = $reportsTable->saveRecord(array(
            'title' => 'Test Report',
            'template' => 'Today is {{dates.0.date}}'
        ));
        $reportSourcesTable = $this->db->getTable(Reports::reportSourcesTableName());
        $reportSourcesTable->saveRecord(array(
            'report' => $report->id(),
            'name' => 'dates',
            'query' => "SELECT CURRENT_DATE AS `date`;",
        ));
        $reports = new Reports($this->db);
        $template = $reports->get_template($report->id());
        $this->assertEquals('Today is ' . date('Y-m-d'), $template->render());
    }

    /**
     * @testdox A report's Template inherits  the report's `file_extension`, `mime_type`, and `title` attributes.
     * @test
     */
    public function fileExtension()
    {
        $reportsTable = $this->db->getTable(Reports::reportsTableName());
        $reports = new Reports($this->db);

        // 1. No file_extension attribute is set, but the others are.
        $report1 = $reportsTable->saveRecord(array(
            'title' => 'Test Report 1',
            'mime_type' => 'text/plain',
        ));
        $template1 = $reports->get_template($report1->id());
        $this->assertNull($template1->file_extension);
        $this->assertEquals('text/plain', $template1->mime_type);

        // 2. A 'GPX' file extension is set, and the default mime_type.
        $report2 = $reportsTable->saveRecord(array(
            'title' => 'Test Report 2',
            'file_extension' => 'gpx',
        ));
        $template2 = $reports->get_template($report2->id());
        $this->assertEquals('gpx', $template2->file_extension);
        $this->assertEquals('text/html', $template2->mime_type);
    }
}
