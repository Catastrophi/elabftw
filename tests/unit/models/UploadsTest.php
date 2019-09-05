<?php declare(strict_types=1);

namespace Elabftw\Models;

class UploadsTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->Users = new Users(1);
        $this->Entity= new Database($this->Users);
    }

    public function testGetIconFromExtension()
    {
        $this->assertEquals('fa-file-archive', $this->Entity->Uploads->getIconFromExtension('zip'));
        $this->assertEquals('fa-file-code', $this->Entity->Uploads->getIconFromExtension('py'));
        $this->assertEquals('fa-file-excel', $this->Entity->Uploads->getIconFromExtension('xls'));
        $this->assertEquals('fa-file-video', $this->Entity->Uploads->getIconFromExtension('avi'));
        $this->assertEquals('fa-file-powerpoint', $this->Entity->Uploads->getIconFromExtension('ppt'));
        $this->assertEquals('fa-file-word', $this->Entity->Uploads->getIconFromExtension('docx'));
        $this->assertEquals('fa-file', $this->Entity->Uploads->getIconFromExtension('elab'));
    }
}
