<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'CompareTrait.php';
require_once 'CompareQueries.php';

use Enjoin\Factory;
use Enjoin\Enjoin;
use Enjoin\Record\Record;
use Enjoin\Exceptions\ValidationException;

class EnjoinTest extends PHPUnit_Framework_TestCase
{

    use CompareTrait;

    private $debugFunction = 'testFindOneEagerNested';

    public function testBootstrap()
    {
        Factory::bootstrap([
            'default' => 'test',
            'connections' => [
                'test' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'database' => getenv('ENJ_DATABASE'),
                    'username' => getenv('ENJ_USERNAME'),
                    'password' => getenv('ENJ_PASSWORD'),
                    'charset' => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix' => ''
                ]
            ],
            'enjoin' => [
                'lang_dir' => 'vendor/caouecs/laravel4-lang'
            ]
        ]);
    }

    /**
     * @depends testBootstrap
     */
    public function testEnjoinGet()
    {
        Enjoin::get('Authors');
        $this->assertArrayHasKey('\Models\Authors', Factory::getInstance()->models);
    }

    /**
     * @depends testBootstrap
     * @return Record
     */
    public function testModelBuild()
    {
        $collection = new stdClass;
        $collection->name = 'J. R. R. Tolkien';
        $it = Enjoin::get('Authors')->build($collection);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
        return $it;
    }

    /**
     * @depends testModelBuild
     * @param Record $it
     */
    public function testNonPersistentRecordSave(Record $it)
    {
        $it->save();
        $this->assertEquals(1, $it->id);
    }

    /**
     * @depends testBootstrap
     * @return Record
     */
    public function testNonPersistentNestedRecordSave()
    {
        $it = Enjoin::get('Authors')->build([
            'name' => 'George Orwell',
            'book' => Enjoin::get('Books')->build([
                'title' => 'Nineteen Eighty Four',
                'year' => 1942
            ])
        ]);
        $it->save();
        $this->assertEquals([2, 1], [$it->id, $it->book->id]);
        return $it;
    }

    /**
     * @depends testNonPersistentNestedRecordSave
     * @param Record $it
     */
    public function testPersistentRecordSave(Record $it)
    {
        $authorName = 'G. Orwell';
        $bookAuthorId = 2;

        $it->name = $authorName;
        $it->book->authors_id = $bookAuthorId;
        $it->save();
        $this->assertEquals([$authorName, $bookAuthorId], [$it->name, $it->book->authors_id]);
    }

    /**
     * @depends testNonPersistentNestedRecordSave
     * @param Record $it
     */
    public function testRecordValidation(Record $it)
    {
        $year = $it->book->year;
        $it->book->year = 3000;
        try {
            $it->book->save();
        } catch (ValidationException $e) {
            $it->book->year = $year;
        }
        $this->assertEquals($year, $it->book->year);
    }

    /**
     * @depends testBootstrap
     * @return Record
     */
    public function testModelCreate()
    {
        $it = Enjoin::get('Publishers')->create(['name' => 'Good Books!']);
        $this->assertEquals(1, $it->id);
        return $it;
    }

    /**
     * @depends testModelBuild
     * @param Record $author
     * @return Record
     */
    public function testRecordAfterSaveMapping(Record $author)
    {
        $book = Enjoin::get('Books')->create([
            'title' => 'The Hobbit: or There and Back Again',
            'year' => 1937,
            'authors_id' => $author->id
        ]);
        $this->assertInstanceOf('Carbon\Carbon', $book->created_at);
        return $book;
    }

    /**
     * @depends testBootstrap
     */
    public function testFindById()
    {
        $this->handleDebug(__FUNCTION__);
        $sql = Enjoin::get('Authors')->findById(1, Enjoin::SQL);
        $this->assertEquals(
            "SELECT `id`, `name`, `created_at`, `updated_at` FROM `authors` AS `authors` WHERE `authors`.`id` = 1",
            $sql
        );
        $it = Enjoin::get('Authors')->findById(1);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
        $this->assertEquals([1, 'J. R. R. Tolkien'], [$it->id, $it->name]);
        $this->assertInstanceOf('Carbon\Carbon', $it->created_at);
        $this->assertInstanceOf('Carbon\Carbon', $it->updated_at);
    }

    /**
     * @depends testModelBuild
     * @param Record $author
     */
    public function testBulkCreateValidation(Record $author)
    {
        $bulk = [[
            'title' => 'testBulkCreateValidation',
            'year' => 3000,
            'authors_id' => $author->id
        ]];
        $passed = false;
        try {
            Enjoin::get('Books')->bulkCreate($bulk);
        } catch (ValidationException $e) {
            $passed = true;
        }
        $this->assertTrue($passed);
    }

    /**
     * @depends testModelBuild
     * @depends testBulkCreateValidation
     * @param Record $author
     */
    public function testBulkCreate(Record $author)
    {
        $bulk = [];
        foreach (array_slice($this->getDataArray('books'), 0, 20) as $book) {
            $book['authors_id'] = $author->id;
            $bulk [] = $book;
        }
        $this->assertTrue(Enjoin::get('Books')->bulkCreate($bulk));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEager()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEager();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEager(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerRequired();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerRequired(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerById()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerById();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerById(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertEquals([
            1, 'J. R. R. Tolkien', 21, 2, 'The Hobbit: or There and Back Again'
        ], [
            $it->id, $it->name, count($it->books), $it->books[0]->id, $it->books[0]->title
        ]);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerByIdRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerByIdRequired();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerByIdRequired(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerByIdMean()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerByIdMean();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerByIdMean(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerMean()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerMean();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerMean(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerMeanRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerMeanRequired();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerMeanRequired(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversed()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversed();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversed(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedRequired();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedRequired(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedById()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedById();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedById(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedByIdRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedByIdRequired();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedByIdRequired(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedByIdMean()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedByIdMean();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedByIdMean(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedMean()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedMean();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedMean(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneEagerReversedMeanRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerReversedMeanRequired();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerReversedMeanRequired(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneComplex()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneComplex();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneComplex(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertInstanceOf('Enjoin\Record\Record', $it);
    }

    /**
     * @depends testBootstrap
     */
    public function testFindOneAndOr()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneAndOr();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneAndOr(), $sql);

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testMockDataA()
    {
        $book = Enjoin::get('Books')->findById(2);
        $this->assertEquals('The Hobbit: or There and Back Again', $book->title);

        $bulk = [];
        foreach (array_slice($this->getDataArray('reviews'), 0, 25) as $review) {
            $review['books_id'] = $book->id;
            $bulk [] = $review;
        }
        $this->assertTrue(Enjoin::get('Reviews')->bulkCreate($bulk));

        $publisher = Enjoin::get('Publishers')->findById(1);
        $this->assertEquals('Good Books!', $publisher->name);

        $bulk = [];
        foreach (array_slice($this->getDataArray('publishers_books'), 0, 5) as $it) {
            $it['publishers_id'] = $publisher->id;
            $it['books_id'] = $book->id;
            $bulk [] = $it;
        }
        $this->assertTrue(Enjoin::get('PublishersBooks')->bulkCreate($bulk));
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerMulti()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerMulti();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindOneEagerMulti(), $sql);

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertEquals(
            [2, 'Nineteen Eighty Four', 'G. Orwell', [], []],
            [$it->authors_id, $it->title, $it->author->name, $it->reviews, $it->publishersBooks]
        );
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerMultiRequired()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerMultiRequired();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerMultiRequired(), $sql));

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertEquals([
            1, 1937, 'J. R. R. Tolkien',
            25, 'ac leo pellentesque ultrices mattis odio donec vitae nisi nam',
            5, 5000
        ], [
            $it->authors_id, $it->year, $it->author->name,
            count($it->reviews), $it->reviews[0]->resource,
            count($it->publishersBooks), $it->publishersBooks[0]->pressrun
        ]);
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerMultiWhere()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerMultiWhere();
        $sql = Enjoin::get('Books')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerMultiWhere(), $sql));

        $it = Enjoin::get('Books')->findOne($params);
        $this->assertEquals([
            2, 1937, 'J. R. R. Tolkien',
            25, 'ac leo pellentesque ultrices mattis odio donec vitae nisi nam',
            3, 90000
        ], [
            $it->id, $it->year, $it->author->name,
            count($it->reviews), $it->reviews[0]->resource,
            count($it->publishersBooks), $it->publishersBooks[0]->pressrun
        ]);
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerNested()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerNested();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerNested(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertEquals([
            1, 'J. R. R. Tolkien',
            1, 'The Hobbit: or There and Back Again', 1937,
            25, 4
        ], [
            $it->id, $it->name,
            count($it->books), $it->books[0]->title, $it->books[0]->year,
            count($it->books[0]->reviews), count($it->books[0]->publishersBooks)
        ]);
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerNestedById()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerNestedById();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerNestedById(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerNestedMean()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerNestedMean();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerNestedMean(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertEquals([
            1, 'J. R. R. Tolkien',
            1, 'The Hobbit: or There and Back Again', 1937,
            25, 4
        ], [
            $it->id, $it->name,
            count($it->books), $it->books[0]->title, $it->books[0]->year,
            count($it->books[0]->reviews), count($it->books[0]->publishersBooks)
        ]);
    }

    /**
     * @depends testMockDataA
     */
    public function testFindOneEagerNestedDeep()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindOneEagerNestedDeep();
        $sql = Enjoin::get('Authors')->findOne($params, Enjoin::SQL);
        $this->assertTrue(CompareQueries::isSame($this->sql_testFindOneEagerNestedDeep(), $sql));

        $it = Enjoin::get('Authors')->findOne($params);
        $this->assertTrue(is_null($it));
    }

    /**
     * @depends testBootstrap
     */
    public function testExpanseModel()
    {
        $this->handleDebug(__FUNCTION__);
        $this->assertEquals('OK', Enjoin::get('Authors')->ping());
    }

    /**
     * @depends testMockDataA
     */
    public function testFindAll()
    {
        $this->handleDebug(__FUNCTION__);
        $sql = Enjoin::get('Authors')->findAll(null, Enjoin::SQL);
        $this->assertEquals(
            "SELECT `id`, `name`, `created_at`, `updated_at` FROM `authors` AS `authors`",
            $sql
        );

        $r = Enjoin::get('Authors')->findAll();
        $this->assertEquals(
            [2, 'J. R. R. Tolkien', 2],
            [count($r), $r[0]->name, $r[1]->id]
        );
    }

    /**
     * @depends testMockDataA
     */
    public function testMockDataB()
    {
        $bulk = [];
        foreach ($this->getDataArray('articles') as $it) {
            $it['authors_id'] = 2;
            $bulk [] = $it;
        }
        $this->assertTrue(Enjoin::get('Articles')->bulkCreate($bulk));
    }

    /**
     * @depends testMockDataB
     */
    public function testFindAllEagerOneThenMany()
    {
        $this->handleDebug(__FUNCTION__);
        $params = $this->params_testFindAllEagerOneThenMany();
        $sql = Enjoin::get('Books')->findAll($params, Enjoin::SQL);
        $this->assertEquals($this->sql_testFindAllEagerOneThenMany(), $sql);

        $r = Enjoin::get('Books')->findAll($params);
        $this->assertEquals(
            [22, 1, 2, 12],
            [count($r), $r[0]->id, $r[0]->author->id, count($r[0]->author->articles)]
        );
    }

    // TODO: test model description getter/setter...
    // TODO: test `hasOne` relation...
    // TODO: test `as` relation...

    /**
     * @param $filename
     * @return array
     */
    private function getDataArray($filename)
    {
        return json_decode($this->getDataFile($filename), true);
    }

    /**
     * @param string $filename
     * @return string
     */
    private function getDataFile($filename)
    {
        return file_get_contents(__DIR__ . '/../data/' . $filename . '.json');
    }

    /**
     * @param string $fnName
     */
    private function handleDebug($fnName)
    {
        if ($fnName === $this->debugFunction) {
            Enjoin::debug(true);
        }
    }

}