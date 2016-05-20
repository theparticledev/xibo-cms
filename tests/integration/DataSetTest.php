<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\Helper\Random;
use Xibo\Tests\LocalWebTestCase;

class DataSetTest extends LocalWebTestCase
{

    protected $startDataSets;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDataSets = (new XiboDataSet($this->getEntityProvider()))->get();
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all datasets that weren't there initially
        $finalDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);
        # Loop over any remaining datasets and nuke them
        foreach ($finalDataSets as $dataSet) {
            /** @var XiboDataSet $dataSet */
            $flag = true;
            foreach ($this->startDataSets as $startData) {
               if ($startData->dataSetId == $dataSet->dataSetId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $dataSet->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $dataSet->dataSetId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /*
    * List all datasets
    */
    public function testListAll()
    {
        $this->client->get('/dataset');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset', [
            'dataSet' => $name,
            'description' => 'PHP Unit Test'
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->dataSet);
        return $object->id;
    }

    /**
     * Test edit
     * @depends testAdd
     */
    public function testEdit()
    {
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');

        $name = Random::generateString(8, 'phpunit');
        $description = 'New description';

        $this->client->put('/dataset/' . $dataSet->dataSetId, [
            'dataSet' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data->dataSet);
        $this->assertSame($description, $object->data->description);

        // Deeper check by querying for dataset again
        $dataSetCheck = (new XiboDataSet($this->getEntityProvider()))->getById($object->id);

        $this->assertSame($name, $dataSetCheck->dataSet);
        $this->assertSame($description, $dataSetCheck->description);

        $dataSet->delete();
    }

    /**
     * @depends testEdit
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known dataSets
        $data1 = (new XiboDataSet($this->getEntityProvider()))->create($name1, 'phpunit description');
        $data2 = (new XiboDataSet($this->getEntityProvider()))->create($name2, 'phpunit description');
        # Delete the one we created last
        $this->client->delete('/dataset/' . $data2->dataSetId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $dataSets = (new XiboDataSet($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startDataSets) + 1, count($dataSets));
        $flag = false;
        foreach ($dataSets as $dataSet) {
            if ($dataSet->dataSetId == $data1->dataSetId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'dataSet ID ' . $data1->dataSetId . ' was not found after deleting a different dataset');
        $data1->delete();
    }

    # TO DO /dataset/import/

    /**
     * Test adding a column
     * return int
     */
    public function testColumnAdd()
    {
        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);

        $dataSet->delete();
    }

    /**
     * Search columns for DataSet
     * @depends testColumnAdd
     */
    public function testListAllColumns()
    {
        // create new dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // add a new column

        $nameCol = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);
        
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        
        // search for columns

        $this->client->get('/dataset/' . $dataSet->dataSetId . '/column');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        $dataSet->delete();
    }

    /**
     * Test edit column
     * @depends testColumnAdd
     */
    public function testColumnEdit()
    {

        // create dataSet

        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // add new column

        $nameCol = Random::generateString(8, 'phpunit');

        $column = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);
        
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $column);
        $object = json_decode($this->client->response->body());

        return $object->id;
        $columnId = $object->id;

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        $this->assertSame(1, $object->data->dataTypeId);
        
        // edit column
        
        $nameNew = Random::generateString(8, 'phpunit');

        $response = $this->client->put('/dataset/' . $dataSet->dataSetId . '/column/' . $columnId, [
            'heading' => $nameNew,
            'listContent' => '',
            'columnOrder' => $object->data->columnOrder,
            'dataTypeId' => $object->data->dataTypeId,
            'dataSetColumnTypeId' => $object->data->dataSetColumnTypeId,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $objectNew = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $objectNew);
        $this->assertObjectHasAttribute('id', $objectNew);
        $this->assertSame($nameNew, $objectNew->data->heading);

        $dataSet->delete();
    }

    /**
     * @param $dataSetId
     * @depends testColumnAdd
     */
    public function testDeleteColumn()
    {
         // create dataSet

        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // add new column

        $nameCol = Random::generateString(8, 'phpunit');

        $column = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $column);
        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        
         $columnId = $object->id;
        // delete column

        $response = $this->client->delete('/dataset/' . $dataSet->dataSetId . '/column/' . $columnId);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $dataSet->delete();
    }

    /*
    * GET data
    */

    public function testGetData()
    {
         // create dataSet

        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        $this->client->get('/dataset/data/' . $dataSet->dataSetId);

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());

        $dataSet->delete();
    }

    /**
     * Test row of data
     */
    public function testRowAdd()
    {
        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        
        return $object->id;

        $data = $this->client->post('dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_ID' => 'whut'
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $data);

        $object = json_decode($this->client->response->body());
        $this->assertSame('test', $object->data->dataSetColumnId_ID);

        $dataSet->delete();

    }

    /**
     * Test edit row
     * @depends testColumnAdd
     */
    public function testRowEdit()
    {

        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');

        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        
        return $object->id;

        // Add new row data

        $data = $this->client->post('dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_ID' => 'test'
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $data);

        $object = json_decode($this->client->response->body());
        $this->assertSame('test', $object->data->dataSetColumnId_ID);
        
        $rowId = $object->id;

        //edit row data
        $dataNew = $this->client->put('dataset/data/' . $dataSet->dataSetId . $rowId, [
            'dataSetColumnId_ID' => ['API EDITED']
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);

        $objectNew = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $objectNew);
        $this->assertObjectHasAttribute('id', $objectNew);
        $this->assertSame('API EDITED', $objectNew->data->dataSetColumnId_ID);

        $dataSet->delete();
    }

    /*
    * delete row data
    */

    public function testRowDelete()
    {
        // Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column assign';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);

        // Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');

        $column = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $nameCol,
            'listContent' => '',
            'columnOrder' => 2,
            'dataTypeId' => 1,
            'dataSetColumnTypeId' => 1,
            'formula' => ''
        ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $column);

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($nameCol, $object->data->heading);
        
        return $object->id;

        // Add new row data

        $data = $this->client->post('dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_ID' => 'test'
            ]);

        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $data);

        $object = json_decode($this->client->response->body());
        $this->assertSame('test', $object->data->dataSetColumnId_ID);
        
        $rowId = $object->id;

        // delete row

        $this->cliet->delete('/dataset/data/' . $dataSet->dataSetId . $rowId);

        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());

        $dataSet->delete();
    }
}
