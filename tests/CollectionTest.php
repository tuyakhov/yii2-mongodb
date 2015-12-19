<?php

namespace yiiunit\extensions\mongodb;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Cursor;
use yii\mongodb\Collection;

/**
 * @group mongodb
 */
class CollectionTest extends TestCase
{
    protected function tearDown()
    {
        $this->dropCollection('customer');
        $this->dropCollection('mapReduceOut');
        parent::tearDown();
    }

    // Tests :

    public function testGetName()
    {
        $collectionName = 'customer';
        $collection = $this->getConnection()->getCollection($collectionName);
        $this->assertEquals($collectionName, $collection->getName());
        $this->assertEquals($this->mongoDbConfig['defaultDatabaseName'] . '.' . $collectionName, $collection->getFullName());
    }

    public function testFind()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $cursor = $collection->find();
        $this->assertTrue($cursor instanceof Cursor);
    }

    public function testInsert()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testInsert
     * @depends testFind
     */
    public function testFindAll()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $cursor = $collection->find();
        $rows = [];
        foreach ($cursor as $row) {
            $rows[] = $row;
        }
        $this->assertEquals(1, count($rows));
        $this->assertEquals($id, $rows[0]['_id']);
    }

    /**
     * @depends testFind
     */
    public function testBatchInsert()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'address' => 'customer 1 address',
            ],
            [
                'name' => 'customer 2',
                'address' => 'customer 2 address',
            ],
        ];
        $insertedRows = $collection->batchInsert($rows);
        $this->assertTrue($insertedRows[0]['_id'] instanceof ObjectID);
        $this->assertTrue($insertedRows[1]['_id'] instanceof ObjectID);
        $this->assertEquals(count($rows), count($collection->find()->toArray()));
    }

    public function testSave()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->save($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testSave
     */
    public function testUpdateBySave()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $newId = $collection->save($data);

        $updatedId = $collection->save($data);
        $this->assertEquals($newId, $updatedId, 'Unable to update data!');

        $data['_id'] = $newId->__toString();
        $updatedId = $collection->save($data);
        $this->assertEquals($newId, $updatedId, 'Unable to updated data by string id!');
    }

    /**
     * @depends testFindAll
     */
    public function testRemove()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $count = $collection->remove(['_id' => $id]);
        $this->assertEquals(1, $count);

        $rows = $this->findAll($collection);
        $this->assertEquals(0, count($rows));
    }

    /**
     * @depends testFindAll
     */
    public function testUpdate()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $newData = [
            'name' => 'new name'
        ];
        $count = $collection->update(['_id' => $id], $newData);
        $this->assertEquals(1, $count);

        list($row) = $this->findAll($collection);
        $this->assertEquals($newData['name'], $row['name']);
    }

    public function testFindAndModify()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
        ];
        $collection->batchInsert($rows);

        // increment field
        $result = $collection->findAndModify(['name' => 'customer 1'], ['$inc' => ['status' => 1]]);
        $this->assertEquals('customer 1', $result['name']);
        $this->assertEquals(1, $result['status']);
        $newResult = $collection->findOne(['name' => 'customer 1']);
        $this->assertEquals(2, $newResult['status']);

        // $set and return modified document
        $result = $collection->findAndModify(
            ['name' => 'customer 2'],
            ['$set' => ['status' => 2]],
            [],
            ['new' => true]
        );
        $this->assertEquals('customer 2', $result['name']);
        $this->assertEquals(2, $result['status']);

        // Full update document
        $data = [
            'name' => 'customer 3',
            'city' => 'Minsk'
        ];
        $result = $collection->findAndModify(
            ['name' => 'customer 2'],
            $data,
            [],
            ['new' => true]
        );
        $this->assertEquals('customer 3', $result['name']);
        $this->assertEquals('Minsk', $result['city']);
        $this->assertTrue(!isset($result['status']));

        // Test exceptions
        $this->setExpectedException('\yii\mongodb\Exception');
        $collection->findAndModify(['name' => 'customer 1'], ['$wrongOperator' => ['status' => 1]]);
    }

    /**
     * @depends testBatchInsert
     */
    public function testMapReduce()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
            [
                'name' => 'customer 2',
                'status' => 2,
                'amount' => 400,
            ],
            [
                'name' => 'customer 2',
                'status' => 3,
                'amount' => 500,
            ],
        ];
        $collection->batchInsert($rows);

        $result = $collection->mapReduce(
            'function () {emit(this.status, this.amount)}',
            'function (key, values) {return Array.sum(values)}',
            'mapReduceOut',
            ['status' => ['$lt' => 3]]
        );
        $this->assertEquals('mapReduceOut', $result);

        $outputCollection = $this->getConnection()->getCollection($result);
        $rows = $this->findAll($outputCollection);
        $expectedRows = [
            [
                '_id' => 1,
                'value' => 300,
            ],
            [
                '_id' => 2,
                'value' => 400,
            ],
        ];
        $this->assertEquals($expectedRows, $rows);
    }

    /**
     * @depends testMapReduce
     */
    public function testMapReduceInline()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'customer 2',
                'status' => 1,
                'amount' => 200,
            ],
            [
                'name' => 'customer 2',
                'status' => 2,
                'amount' => 400,
            ],
            [
                'name' => 'customer 2',
                'status' => 3,
                'amount' => 500,
            ],
        ];
        $collection->batchInsert($rows);

        $result = $collection->mapReduce(
            'function () {emit(this.status, this.amount)}',
            'function (key, values) {return Array.sum(values)}',
            ['inline' => true],
            ['status' => ['$lt' => 3]]
        );
        $expectedRows = [
            [
                '_id' => 1,
                'value' => 300,
            ],
            [
                '_id' => 2,
                'value' => 400,
            ],
        ];
        $this->assertEquals($expectedRows, $result);
    }

    public function testCreateIndex()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $columns = [
            'name',
            'status' => Collection::ASCENDING,
        ];
        $this->assertTrue($collection->createIndex($columns));
        $indexInfo = $collection->mongoCollection->listIndexes();
        $this->assertEquals(1, count($indexInfo));
    }

    /**
     * @depends testCreateIndex
     */
    public function testDropIndex()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $collection->createIndex('name');
        $this->assertTrue($collection->dropIndex('name'));
        $indexInfo = $collection->mongoCollection->listIndexes();
        $this->assertEquals(1, count($indexInfo));

        $this->setExpectedException('\yii\mongodb\Exception');
        $collection->dropIndex('name');
    }

    /**
     * @depends testCreateIndex
     */
    public function testDropAllIndexes()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $collection->createIndex('name');
        $this->assertEquals(2, $collection->dropAllIndexes());
        $indexInfo = $collection->mongoCollection->listIndexes();
        $this->assertEquals(1, count($indexInfo));
    }

    /**
     * @depends testBatchInsert
     * @depends testCreateIndex
     */
    public function testFullTextSearch()
    {
        if (version_compare('2.4', $this->getServerVersion(), '>')) {
            $this->markTestSkipped("Mongo Server 2.4 required.");
        }

        $collection = $this->getConnection()->getCollection('customer');

        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
                'amount' => 100,
            ],
            [
                'name' => 'some customer',
                'status' => 1,
                'amount' => 200,
            ],
            [
                'name' => 'no search keyword',
                'status' => 1,
                'amount' => 200,
            ],
        ];
        $collection->batchInsert($rows);
        $collection->createIndex(['name' => 'text']);

//        $result = $collection->fullTextSearch('customer');
//        $this->assertNotEmpty($result);
//        $this->assertCount(2, $result);
    }

    /**
     * @depends testInsert
     * @depends testFind
     */
    public function testFindByNotObjectId()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
        ];
        $id = $collection->insert($data);

        $cursor = $collection->find(['_id' => (string) $id]);
        $this->assertTrue($cursor instanceof Cursor);
        $row = current($cursor->toArray());
        $this->assertEquals($id, $row['_id']);

        $cursor = $collection->find(['_id' => '507f191e810c19729de860ea']);
        $this->assertTrue($cursor instanceof Cursor);
        $this->assertEquals(0, count($cursor->toArray()));
    }

    /**
     * @depends testInsert
     *
     * @see https://github.com/yiisoft/yii2/issues/2548
     */
    public function testInsertMongoBin()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $fileName = __FILE__;
        $data = [
            'name' => 'customer 1',
            'address' => 'customer 1 address',
            'binData' => new Binary(file_get_contents($fileName), 2),
        ];
        $id = $collection->insert($data);
        $this->assertTrue($id instanceof ObjectID);
        $this->assertNotEmpty($id->__toString());
    }

    /**
     * @depends testBatchInsert
     */
    public function testDistinct()
    {
        $collection = $this->getConnection()->getCollection('customer');

        $rows = [
            [
                'name' => 'customer 1',
                'status' => 1,
            ],
            [
                'name' => 'customer 1',
                'status' => 1,
            ],
            [
                'name' => 'customer 3',
                'status' => 2,
            ],
        ];
        $collection->batchInsert($rows);

        $rows = $collection->distinct('status');
        $this->assertFalse($rows === false);
        $this->assertCount(2, $rows);

        $rows = $collection->distinct('status', ['status' => 1]);
        $this->assertFalse($rows === false);
        $this->assertCount(1, $rows);
    }
}
