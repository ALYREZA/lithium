<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\data\source;

use lithium\data\Connections;
use lithium\data\model\Query;
use lithium\data\entity\Record;
use lithium\tests\mocks\data\model\MockDatabase;
use lithium\tests\mocks\data\model\MockDatabasePost;
use lithium\tests\mocks\data\model\MockDatabaseComment;
use lithium\tests\mocks\data\model\MockDatabaseTagging;
use lithium\tests\mocks\data\model\MockDatabasePostRevision;
use lithium\tests\mocks\data\model\mock_database\MockResult;
use lithium\tests\mocks\data\model\MockGallery;
use lithium\tests\mocks\data\model\MockImage;
use lithium\tests\mocks\data\model\MockImageTag;
use lithium\tests\mocks\data\model\MockTag;

class DatabaseTest extends \lithium\test\Unit {

	protected $_db = null;

	protected $_configs = array();

	protected $_model = 'lithium\tests\mocks\data\model\MockDatabasePost';
	protected $_comment = 'lithium\tests\mocks\data\model\MockDatabaseComment';
	protected $_gallery = 'lithium\tests\mocks\data\model\MockGallery';
	protected $_imageTag = 'lithium\tests\mocks\data\model\MockImageTag';

	public function setUp() {
		$this->_db = new MockDatabase();
		Connections::add('mockconn', array('object' => $this->_db));

		$config = array('meta' => array('connection' => 'mockconn'));
		MockDatabasePost::config($config);
		MockDatabaseComment::config($config);
		MockDatabaseTagging::config($config);
		MockDatabasePostRevision::config($config);
		MockGallery::config($config);
		MockImage::config($config);
		MockImageTag::config($config);
		MockTag::config($config);
	}

	public function tearDown() {
		Connections::remove('mockconn');
		MockDatabasePost::reset();
		MockDatabaseComment::reset();
		MockDatabaseTagging::reset();
		MockDatabasePostRevision::reset();
		MockGallery::reset();
		MockImage::reset();
		MockImageTag::reset();
		MockTag::reset();
	}

	public function testDefaultConfig() {
		$expected = array(
			'persistent'  => true,
			'host'        => 'localhost',
			'login'       => 'root',
			'password'    => '',
			'database'    => null,
			'encoding'    => null,
			'dsn'         => null,
			'options'     => array(),
			'autoConnect' => true,
			'init'        => true
		);
		$result = $this->_db->testConfig();
		$this->assertEqual($expected, $result);
	}

	public function testModifyConfig() {
		$db = new MockDatabase(array('host' => '127.0.0.1', 'login' => 'bob'));
		$expected = array(
			'persistent'    => true,
			'host'          => '127.0.0.1',
			'login'         => 'bob',
			'password'      => '',
			'database'      => null,
			'encoding'      => null,
			'dsn'           => null,
			'options'       => array(),
			'autoConnect'   => true,
			'init'          => true
		);
		$result = $db->testConfig();
		$this->assertEqual($expected, $result);
	}

	public function testName() {
		$result = $this->_db->name("name");
		$this->assertEqual("{name}", $result);

		$result = $this->_db->name("Model.name");
		$this->assertEqual("{Model}.{name}", $result);

		$result = $this->_db->name("Model.name name");
		$this->assertEqual("{Model}.{name name}", $result);
	}

	public function testValueWithSchema() {
		$result = $this->_db->value(null);
		$this->assertIdentical('NULL', $result);

		$result = $this->_db->value('string', array('type' => 'string'));
		$this->assertEqual("'string'", $result);

		$result = $this->_db->value('true', array('type' => 'boolean'));
		$this->assertIdentical(1, $result);

		$result = $this->_db->value('1', array('type' => 'integer'));
		$this->assertIdentical(1, $result);

		$result = $this->_db->value('1.1', array('type' => 'float'));
		$this->assertIdentical(1.1, $result);

		$result = $this->_db->value('1', array('type' => 'string'));
		$this->assertIdentical("'1'", $result);

		$result = $this->_db->value((object) 'REGEXP "^fo$"');
		$this->assertIdentical('REGEXP "^fo$"', $result);

		$result = $this->_db->value((object) 'CURRENT_TIMESTAMP', array('type' => 'timestamp'));
		$this->assertIdentical('CURRENT_TIMESTAMP', $result);

		$result = $this->_db->value('2012-05-25 22:44:00', array('type' => 'timestamp'));
		$this->assertIdentical("'2012-05-25 22:44:00'", $result);

		$result = $this->_db->value('2012-05-25', array('type' => 'date'));
		$this->assertIdentical("'2012-05-25'", $result);

		$result = $this->_db->value('now', array('type' => 'timestamp'));
		$this->assertPattern("/^'\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}'/", $result);

		$result = $this->_db->value('now', array('type' => 'date'));
		$this->assertPattern("/^'\d{4}-\d{2}-\d{2}'/", $result);

		$result = $this->_db->value('now', array('type' => 'time'));
		$this->assertPattern("/^'\d{2}:\d{2}:\d{2}'/", $result);
	}

	public function testValueByIntrospect() {
		$result = $this->_db->value("string");
		$this->assertIdentical("'string'", $result);

		$result = $this->_db->value(true);
		$this->assertIdentical(1, $result);

		$result = $this->_db->value('1');
		$this->assertIdentical(1, $result);

		$result = $this->_db->value('1.1');
		$this->assertIdentical(1.1, $result);
	}

	public function testSchema() {
		$model = $this->_model;
		$model::config();
		$modelName = '';
		$expected = array($modelName => array('id', 'author_id', 'title', 'created'));
		$result = $this->_db->schema(new Query(compact('model')));
		$this->assertEqual($expected, $result);

		$query = new Query(compact('model') + array('fields' => '*'));
		$result = $this->_db->schema($query);
		$this->assertEqual($expected, $result);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('MockDatabaseComment'),
			'with' => array('MockDatabaseComment')
		));
		$expected = array(
			'' => array('id'),
			'MockDatabaseComment' => array(
				'id', 'post_id', 'author_id', 'body', 'created'
			)
		);
		$result = $this->_db->schema($query);
		$this->assertEqual($expected, $result);

		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => 'MockDatabaseComment'
		);
		$options['fields'] = array('id', 'title');
		$result = $this->_db->schema(new Query($options));

		$expected = array($modelName => $options['fields']);
		$this->assertEqual($expected, $result);

		$options['fields'] = array(
			'MockDatabasePost.id',
			'MockDatabasePost.title',
			'MockDatabaseComment.body'
		);
		$result = $this->_db->schema(new Query($options));
		$expected = array(
			$modelName => array('id', 'title'),
			'MockDatabaseComment' => array('body')
		);
		$this->assertEqual($expected, $result);

		$options['fields'] = array(
			'MockDatabasePost' => array('id', 'title'),
			'MockDatabaseComment' => array('body', 'created')
		);
		$result = $this->_db->schema(new Query($options));
		$expected = array(
			$modelName => array('id', 'title'),
			'MockDatabaseComment' => array('body', 'created')
		);
		$this->assertEqual($expected, $result);

		$options['fields'] = array('MockDatabasePost', 'MockDatabaseComment');
		$result = $this->_db->schema(new Query($options));
		$expected = array(
			$modelName => array('id', 'author_id', 'title', 'created'),
			'MockDatabaseComment' => array('id', 'post_id', 'author_id', 'body', 'created')
		);
		$this->assertEqual($expected, $result);
	}

	public function testSchemaFromManualFieldList() {
		$fields = array('id', 'name', 'created');
		$result = $this->_db->schema(new Query(compact('fields')));
		$this->assertEqual(array('' => $fields), $result);
	}

	public function testSimpleQueryRender() {
		$fieldList = '{MockDatabasePost}.{id}, {MockDatabasePost}.{title},';
		$fieldList .= ' {MockDatabasePost}.{created}';
		$table = '{mock_database_posts} AS {MockDatabasePost}';

		$result = $this->_db->renderCommand(new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('id', 'title', 'created')
		)));
		$this->assertEqual("SELECT {$fieldList} FROM {$table};", $result);

		$result = $this->_db->renderCommand(new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('id', 'title', 'created'),
			'limit' => 1
		)));
		$this->assertEqual("SELECT {$fieldList} FROM {$table} LIMIT 1;", $result);

		$result = $this->_db->renderCommand(new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('id', 'title', 'created'),
			'limit' => 1,
			'conditions' => 'Post.id = 2'
		)));
		$this->assertEqual("SELECT {$fieldList} FROM {$table} WHERE Post.id = 2 LIMIT 1;", $result);
	}

	public function testNestedQueryConditions() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('MockDatabasePost.title', 'MockDatabasePost.body'),
			'conditions' => array('Post.id' => new Query(array(
				'type' => 'read',
				'fields' => array('post_id'),
				'model' => 'lithium\tests\mocks\data\model\MockDatabaseTagging',
				'conditions' => array('MockDatabaseTag.tag' => array('foo', 'bar', 'baz'))
			)))
		));
		$result = $this->_db->renderCommand($query);

		$expected = "SELECT {MockDatabasePost}.{title}, {MockDatabasePost}.{body} FROM";
		$expected .= " {mock_database_posts} AS {MockDatabasePost} WHERE {Post}.{id} IN";
		$expected .= " (SELECT {MockDatabaseTagging}.{post_id} FROM {mock_database_taggings} AS ";
		$expected .= "{MockDatabaseTagging} WHERE {MockDatabaseTag}.{tag} IN";
		$expected .= " ('foo', 'bar', 'baz'));";
		$this->assertEqual($expected, $result);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('MockDatabasePost.title', 'MockDatabasePost.body'),
			'conditions' => array('Post.id' => array('!=' => new Query(array(
				'type' => 'read',
				'fields' => array('post_id'),
				'model' => 'lithium\tests\mocks\data\model\MockDatabaseTagging',
				'conditions' => array('MockDatabaseTag.tag' => array('foo', 'bar', 'baz'))
			))))
		));
		$result = $this->_db->renderCommand($query);

		$expected = "SELECT {MockDatabasePost}.{title}, {MockDatabasePost}.{body} FROM";
		$expected .= " {mock_database_posts} AS {MockDatabasePost} WHERE ({Post}.{id} NOT IN";
		$expected .= " (SELECT {MockDatabaseTagging}.{post_id} FROM {mock_database_taggings} AS ";
		$expected .= "{MockDatabaseTagging} WHERE {MockDatabaseTag}.{tag} IN ";
		$expected .= "('foo', 'bar', 'baz')));";
		$this->assertEqual($expected, $result);

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array(
				'or' => array(
					'{MockDatabasePost}.{id}' => 'value1',
					'{MockDatabasePost}.{title}' => 'value2'
				)
			)
		));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ";
		$sql .= "({MockDatabasePost}.{id} = 'value1' OR {MockDatabasePost}.{title} = 'value2');";
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testCastingQueryConditionsWithSchemaWithAlias() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'conditions' => array(
				'MockDatabasePost.title' => '007'
			)
		));
		$result = $this->_db->renderCommand($query);

		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ";
		$sql .= "{MockDatabasePost}.{title} = '007';";
		$this->assertEqual($sql, $result);
	}

	public function testQueryJoin() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'fields' => array('MockDatabasePost.title', 'MockDatabasePost.body'),
			'conditions' => array('MockDatabaseTag.tag' => array('foo', 'bar', 'baz')),
			'joins' => array(new Query(array(
				'model' => 'lithium\tests\mocks\data\model\MockDatabaseTag',
				'constraints' => '{MockDatabaseTagging}.{tag_id} = {MockDatabaseTag}.{id}'
			)))
		));
		$result = $this->_db->renderCommand($query);

		$expected = "SELECT {MockDatabasePost}.{title}, {MockDatabasePost}.{body} FROM";
		$expected .= " {mock_database_posts} AS {MockDatabasePost} JOIN {mock_database_tags} AS";
		$expected .= " {MockDatabaseTag} ON ";
		$expected .= "{MockDatabaseTagging}.{tag_id} = {MockDatabaseTag}.{id}";
		$expected .= " WHERE {MockDatabaseTag}.{tag} IN ('foo', 'bar', 'baz');";
		$this->assertEqual($expected, $result);
	}

	public function testItem() {
		$model = $this->_model;
		$data = array('title' => 'new post', 'content' => 'This is a new post.');
		$result = $model::create($data, array('defaults' => false));
		$this->assertEqual($data, $result->data());
	}

	public function testCreate() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('title' => 'new post', 'body' => 'the body')
		));
		$query = new Query(compact('entity') + array(
			'type' => 'create',
			'model' => $this->_model
		));
		$hash = $query->export($this->_db);
		ksort($hash);
		$expected = sha1(serialize($hash));

		$result = $this->_db->create($query);
		$this->assertTrue($result);
		$result = $query->entity()->id;
		$this->assertEqual($expected, $result);

		$expected = "INSERT INTO {mock_database_posts} ({title}, {body})";
		$expected .= " VALUES ('new post', 'the body');";
		$result = $this->_db->sql;
		$this->assertEqual($expected, $result);
	}

	public function testCreateGenericSyntax() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('data' => array('title' => 'new post', 'body' => 'the body'))
		));
		$query = new Query(compact('entity') + array(
			'type' => 'create',
			'model' => $this->_model
		));
		$hash = $query->export($this->_db);
		ksort($hash);
		$expected = sha1(serialize($hash));

		$result = $this->_db->create($query);
		$this->assertTrue($result);
		$result = $query->entity()->id;
		$this->assertEqual($expected, $result);

		$expected = "INSERT INTO {mock_database_posts} ({title}, {body})";
		$expected .= " VALUES ('new post', 'the body');";
		$result = $this->_db->sql;
		$this->assertEqual($expected, $result);
	}

	public function testCreateWithValueBySchema() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('title' => '007', 'body' => 'the body')
		));
		$query = new Query(compact('entity') + array(
			'type' => 'create',
			'model' => $this->_model
		));
		$hash = $query->export($this->_db);
		ksort($hash);
		$expected = sha1(serialize($hash));

		$result = $this->_db->create($query);
		$this->assertTrue($result);
		$result = $query->entity()->id;
		$this->assertEqual($expected, $result);

		$expected = "INSERT INTO {mock_database_posts} ({title}, {body})";
		$expected .= " VALUES ('007', 'the body');";
		$result = $this->_db->sql;
		$this->assertEqual($expected, $result);
	}

	public function testCreateWithKey() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('id' => 1, 'title' => 'new post', 'body' => 'the body')
		));
		$query = new Query(compact('entity') + array('type' => 'create'));
		$expected = 1;

		$result = $this->_db->create($query);
		$this->assertTrue($result);
		$result = $query->entity()->id;
		$this->assertEqual($expected, $result);

		$expected = "INSERT INTO {mock_database_posts} ({id}, {title}, {body})";
		$expected .= " VALUES (1, 'new post', 'the body');";
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testReadWithQueryStringReturnResource() {
		$result = $this->_db->read('SELECT * from mock_database_posts AS MockDatabasePost;', array(
			'return' => 'resource'
		));
		$this->assertNotEmpty($result);

		$expected = "SELECT * from mock_database_posts AS MockDatabasePost;";
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testCalculation() {
		$options = array('type' => 'read', 'model' => $this->_model);
		$this->expectException('Undefined offset: 0');
		$result = $this->_db->calculation('count', new Query($options), $options);
		$expected = 'SELECT COUNT(*) as count FROM {mock_database_posts} AS {MockDatabasePost};';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testReadWithQueryStringReturnArrayWithSchema() {
		$result = $this->_db->read('SELECT * FROM {:table} WHERE user_id = {:uid};', array(
			'table' => 'mock_database_posts',
			'uid' => '3',
			'schema' => array('id', 'title', 'text')
		));
		$expected = 'SELECT * FROM \'mock_database_posts\' WHERE user_id = 3;';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testReadWithQueryObjectRecordSet() {
		$query = new Query(array('type' => 'read', 'model' => $this->_model));
		$result = $this->_db->read($query);
		$this->assertInstanceOf('lithium\data\collection\RecordSet', $result);

		$expected = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost};";
		$result = $this->_db->sql;
		$this->assertEqual($expected, $result);
	}

	public function testReadWithQueryObjectArray() {
		$query = new Query(array('type' => 'read', 'model' => $this->_model));
		$result = $this->_db->read($query, array('return' => 'array'));
		$this->assertInternalType('array', $result);

		$expected = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost};";
		$result = $this->_db->sql;
		$this->assertEqual($expected, $result);
	}

	public function testUpdate() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('id' => 1, 'title' => 'new post', 'body' => 'the body'),
			'exists' => true
		));
		$query = new Query(compact('entity') + array('type' => 'update'));
		$result = $this->_db->update($query);

		$this->assertTrue($result);
		$this->assertEqual(1, $query->entity()->id);

		$expected = "UPDATE {mock_database_posts} SET";
		$expected .= " {id} = 1, {title} = 'new post', {body} = 'the body' WHERE {id} = 1;";
		$this->assertEqual($expected, $this->_db->sql);

		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('id' => 2, 'count' => (object) '{count} + 1'),
			'exists' => true
		));
		$query = new Query(compact('entity') + array('type' => 'update'));
		$result = $this->_db->update($query);

		$this->assertTrue($result);
		$this->assertEqual(2, $query->entity()->id);

		$expected = "UPDATE {mock_database_posts} SET";
		$expected .= " {id} = 2, {count} = {count} + 1 WHERE {id} = 2;";
		$this->assertEqual($expected, $this->_db->sql);

		$query = new Query(array(
			'type' => 'update',
			'data' => array('modified' => (object) 'NOW()'),
			'model' => $this->_model
		));
		$sql = "UPDATE {mock_database_posts} SET {modified} = NOW();";
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testUpdateWithValueBySchema() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('id' => 1, 'title' => '007', 'body' => 'the body'),
			'exists' => true
		));
		$query = new Query(compact('entity') + array('type' => 'update'));
		$result = $this->_db->update($query);

		$this->assertTrue($result);
		$this->assertEqual(1, $query->entity()->id);

		$expected = "UPDATE {mock_database_posts} SET";
		$expected .= " {id} = 1, {title} = '007', {body} = 'the body' WHERE {id} = 1;";
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testDelete() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('id' => 1, 'title' => 'new post', 'body' => 'the body'),
			'exists' => true
		));
		$query = new Query(compact('entity') + array('type' => 'delete'));
		$this->assertTrue($entity->exists());
		$this->assertTrue($this->_db->delete($query));
		$this->assertEqual(1, $query->entity()->id);

		$expected = "DELETE FROM {mock_database_posts} WHERE {id} = 1;";
		$this->assertEqual($expected, $this->_db->sql);
		$this->assertFalse($entity->exists());
	}

	public function testOrder() {
		$query = new Query(array('model' => $this->_model));

		$result = $this->_db->order("foo_bar", $query);
		$expected = 'ORDER BY {foo_bar} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order("title", $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order("title", $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array("title"), $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array("title" => "desc"), $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} desc';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array("title" => "dasc"), $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array("title" => array()), $query);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array('author_id', "title" => "DESC"), $query);
		$expected = 'ORDER BY {MockDatabasePost}.{author_id} ASC, {MockDatabasePost}.{title} DESC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array(), $query);
		$expected = '';
		$this->assertEqual($expected, $result);
	}

	public function testOrderOnRelated() {
		$query = new Query(array(
			'model' => $this->_model,
			'with' => array('MockDatabaseComment')
		));

		$result = $this->_db->order('MockDatabaseComment.created DESC', $query);
		$expected = 'ORDER BY {MockDatabaseComment}.{created} DESC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(array('MockDatabaseComment.created' => 'DESC'), $query);
		$expected = 'ORDER BY {MockDatabaseComment}.{created} DESC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(
			array(
				'MockDatabasePost.title' => 'ASC',
				'MockDatabaseComment.created' => 'DESC'
			),
			$query
		);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC, {MockDatabaseComment}.{created} DESC';
		$this->assertEqual($expected, $result);

		$result = $this->_db->order(
			array(
				'title' => 'ASC',
				'MockDatabaseComment.created' => 'DESC'
			),
			$query
		);
		$expected = 'ORDER BY {MockDatabasePost}.{title} ASC, {MockDatabaseComment}.{created} DESC';
		$this->assertEqual($expected, $result);
	}

	public function testScopedDelete() {
		$query = new Query(array(
			'type' => 'delete',
			'conditions' => array('published' => false),
			'model' => $this->_model
		));
		$sql = 'DELETE FROM {mock_database_posts} WHERE {published} = 0;';
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testScopedUpdate() {
		$query = new Query(array(
			'type' => 'update',
			'conditions' => array('expires' => array('>=' => '2010-05-13')),
			'data' => array('published' => false, 'comments' => null),
			'model' => $this->_model
		));
		$sql = "UPDATE {mock_database_posts} SET {published} = 0, {comments} = NULL WHERE ";
		$sql .= "({expires} >= '2010-05-13');";
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testQueryOperators() {
		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'score' => array('between' => array(90, 100))
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ({score} ";
		$sql .= "BETWEEN 90 AND 100);";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'score' => array('>' => 90, '<' => 100)
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ";
		$sql .= "({score} > 90 AND {score} < 100);";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'score' => array('!=' => array(98, 99, 100))
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} ";
		$sql .= "WHERE ({score} NOT IN (98, 99, 100));";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'scorer' => array('like' => '%howard%')
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} ";
		$sql .= "WHERE ({scorer} LIKE '%howard%');";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$conditions = "custom conditions string";
		$query = new Query(compact('conditions') + array(
			'type' => 'read', 'model' => $this->_model
		));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE {$conditions};";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array(
				'field' => array('like' => '%value%', 'not like' => '%value2%')
			)
		));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ";
		$sql .= "({field} LIKE '%value%' AND {field} NOT LIKE '%value2%');";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'scorer' => array('is' => null)
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} ";
		$sql .= "WHERE ({scorer} IS NULL);";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => array(
			'scorer' => array('is not' => null)
		)));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} ";
		$sql .= "WHERE ({scorer} IS NOT NULL);";
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testConditions() {
		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array(
				'or' => array(
					'id' => 'value1',
					'title' => 'value2',
					'and' => array(
						'author_id' => '1',
						'created' => '2012-05-25 23:41:00'
					),
					array('title' => 'value2'),
					array('title' => null)
				),
				'id' => '3',
				'author_id' => false
			)
		));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ";
		$sql .= "({MockDatabasePost}.{id} = 0 OR {MockDatabasePost}.{title} = 'value2' OR ";
		$sql .= "({MockDatabasePost}.{author_id} = 1 AND {MockDatabasePost}.{created} = ";
		$sql .= "'2012-05-25 23:41:00') OR ({MockDatabasePost}.{title} = 'value2') OR ";
		$sql .= "({MockDatabasePost}.{title} IS NULL)) AND {MockDatabasePost}.{id} = 3 AND ";
		$sql .= "{MockDatabasePost}.{author_id} = 0;";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array('title' => array('0900'))
		));

		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost}';
		$sql .= ' WHERE {title} IN (\'0900\');';
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ';
		$sql .= 'lower(title) = \'test\';';

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array('lower(title)' => 'test')
		));

		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array((object) 'lower(title) = \'test\'')
		));

		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} WHERE ';
		$sql .= 'lower(title) = REGEXP \'^test$\';';

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array((object) 'lower(title) = REGEXP \'^test$\'')
		));

		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'conditions' => array('lower(title)' => (object) 'REGEXP \'^test$\'')
		));

		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testHaving() {
		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'having' => array(
				'or' => array(
					'id' => 'value1',
					'title' => 'value2',
					'and' => array(
						'author_id' => '1',
						'created' => '2012-05-25 23:41:00'
					),
					array('title' => 'value2'),
					array('title' => null)
				),
				'id' => '3',
				'author_id' => false
			)
		));
		$sql = "SELECT * FROM {mock_database_posts} AS {MockDatabasePost} HAVING ";
		$sql .= "({MockDatabasePost}.{id} = 0 OR {MockDatabasePost}.{title} = 'value2' OR ";
		$sql .= "({MockDatabasePost}.{author_id} = 1 AND {MockDatabasePost}.{created} = ";
		$sql .= "'2012-05-25 23:41:00') OR ({MockDatabasePost}.{title} = 'value2') OR ";
		$sql .= "({MockDatabasePost}.{title} IS NULL)) AND {MockDatabasePost}.{id} = 3 AND ";
		$sql .= "{MockDatabasePost}.{author_id} = 0;";
		$this->assertEqual($sql, $this->_db->renderCommand($query));

		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model,
			'having' => array('title' => array('0900'))
		));

		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost}';
		$sql .= ' HAVING {title} IN (\'0900\');';
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testConstraints() {
		$model = $this->_model;
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array(
				'MockDatabaseComment' => array(
					'constraints' => array(
						'or' => array(
							array('custom_id' => 'MockDatabasePost.value_id'),
							array('custom_id' => 'id'),
							'and' => array(
								'id' => 'MockDatabasePost.id',
								'title' => 'MockDatabasePost.title'
							),
							array('title' => (object) $this->_db->value('value2')),
							array('title' => null)
						),
						'id' => 5
					)
				)
			)
		));

		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LEFT JOIN ';
		$sql .= '{mock_database_comments} AS {MockDatabaseComment} ON ';
		$sql .= '(({MockDatabasePost}.{custom_id} = {MockDatabasePost}.{value_id}) OR ';
		$sql .= '({MockDatabasePost}.{custom_id} = {MockDatabaseComment}.{id}) OR ';
		$sql .= '({MockDatabasePost}.{id} = {MockDatabasePost}.{id} ';
		$sql .= 'AND {MockDatabasePost}.{title} = {MockDatabasePost}.{title}) ';
		$sql .= 'OR ({MockDatabasePost}.{title} = \'value2\') ';
		$sql .= 'OR ({MockDatabasePost}.{title} IS NULL)) AND {MockDatabasePost}.{id} = 5;';

		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testReadConditionsWithModel() {
		$model = $this->_model;
		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'conditions' => array('id' => 1, 'MockDatabaseComment.id' => 2),
			'with' => array('MockDatabaseComment')
		);
		$result = $this->_db->read(new Query($options), $options);
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LEFT JOIN ';
		$expected .= '{mock_database_comments} AS {MockDatabaseComment} ON ';
		$expected .= '{MockDatabasePost}.{id} = {MockDatabaseComment}.{mock_database_post_id} ';
		$expected .= 'WHERE {MockDatabasePost}.{id} = 1 AND {MockDatabaseComment}.{id} = 2;';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testFields() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabaseComment')
		));

		$fields = array('id', 'title');
		$result = $this->_db->fields($fields, $query);
		$expected = '{MockDatabasePost}.{id}, {MockDatabasePost}.{title}';
		$this->assertEqual($expected,$result);

		$fields = array(
			'MockDatabasePost' => array('id', 'title', 'created'),
			'MockDatabaseComment' => array('body')
		);
		$result = $this->_db->fields($fields, $query);
		$expected = '{MockDatabasePost}.{id}, {MockDatabasePost}.{title},';
		$expected .= ' {MockDatabasePost}.{created}, {MockDatabaseComment}.{body}';
		$this->assertEqual($expected,$result);

		$fields = array('MockDatabasePost', 'MockDatabaseComment');
		$result = $this->_db->fields($fields, $query);
		$expected = '{MockDatabasePost}.*, {MockDatabaseComment}.*';
		$this->assertEqual($expected, $result);

		$fields = array('MockDatabasePost.id as idPost', 'MockDatabaseComment.id AS idComment');
		$result = $this->_db->fields($fields, $query);
		$expected = '{MockDatabasePost}.{id} as idPost, {MockDatabaseComment}.{id} as idComment';
		$this->assertEqual($expected, $result);

		$expected = array('' => array('idPost'), 'MockDatabaseComment' => array('idComment'));
		$this->assertEqual($expected, $query->map());

		$fields = array(array('count(MockDatabasePost.id)'));
		$expected = 'count(MockDatabasePost.id)';
		$result = $this->_db->fields($fields, $query);
		$this->assertEqual($expected, $result);

		$fields = array(array((object) 'count(MockDatabasePost.id)'));
		$expected = 'count(MockDatabasePost.id)';
		$result = $this->_db->fields($fields, $query);
		$this->assertEqual($expected, $result);
	}

	public function testFieldsWithEmptyAlias() {
		$query = new Query();
		$result = $this->_db->fields(array('id', 'name', 'created'), $query);
		$expected = '{id}, {name}, {created}';
		$this->assertEqual($expected, $result);
	}

	public function testRawConditions() {
		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'conditions' => null));
		$this->assertEmpty($this->_db->conditions(5, $query));
		$this->assertEmpty($this->_db->conditions(null, $query));
		$this->assertEqual("WHERE CUSTOM", $this->_db->conditions("CUSTOM", $query));
	}

	public function testRawHaving() {
		$query = new Query(array('type' => 'read', 'model' => $this->_model, 'having' => null));
		$this->assertEmpty($this->_db->having(5, $query));
		$this->assertEmpty($this->_db->having(null, $query));
		$this->assertEqual("HAVING CUSTOM", $this->_db->having("CUSTOM", $query));
	}

	public function testRelationshipGeneration() {
		$comment = 'lithium\tests\mocks\data\model\MockDatabaseComment';

		$hasMany = $this->_db->relationship($this->_model, 'hasMany', 'Comments', array(
			'to' => $comment
		));
		$this->assertEqual(array('id' => 'mock_database_post_id'), $hasMany->key());
		$this->assertEqual('comments', $hasMany->fieldName());

		$belongsTo = $this->_db->relationship($comment, 'belongsTo', 'Posts', array(
			'to' => $this->_model
		));
		$this->assertEqual(array('post_id' => 'id'), $belongsTo->key());
		$this->assertEqual('post', $belongsTo->fieldName());
	}

	public function testRelationshipGenerationWithNullConstraint() {
		$postRevision = 'lithium\tests\mocks\data\model\MockDatabasePostRevision';

		$hasMany = $this->_db->relationship($this->_model, 'hasMany', 'PostRevisions', array(
			'to' => $postRevision,
			'constraints' => array('MockDatabasePostRevision.deleted' => null)
		));
		$this->assertEqual(array('id' => 'mock_database_post_id'), $hasMany->key());
		$this->assertEqual('post_revisions', $hasMany->fieldName());

		$expected = array(
			'MockDatabasePostRevision.deleted' => null,
			'MockDatabasePost.id' => 'PostRevisions.mock_database_post_id'
		);
		$result = $this->_db->on($hasMany);
		$this->assertEqual($expected, $result);

		$belongsTo = $this->_db->relationship($postRevision, 'belongsTo', 'Posts', array(
			'to' => $this->_model
		));
		$this->assertEqual(array('post_id' => 'id'), $belongsTo->key());
		$this->assertEqual('post', $belongsTo->fieldName());
	}

	public function testInvalidQueryType() {
		$this->expectException('Invalid query type `fakeType`.');
		$this->_db->read(new Query(array('type' => 'fakeType')));
	}

	public function testReadWithRelationship() {
		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabaseComment')
		);
		$result = $this->_db->read(new Query($options), $options);
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LEFT JOIN ';
		$expected .= '{mock_database_comments} AS {MockDatabaseComment} ON ';
		$expected .= '{MockDatabasePost}.{id} = {MockDatabaseComment}.{mock_database_post_id};';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testReadWithRelationshipWithNullConstraint() {
		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabasePostRevision')
		);
		$result = $this->_db->read(new Query($options), $options);
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LEFT JOIN ';
		$expected .= '{mock_database_post_revisions} AS {MockDatabasePostRevision} ON ';
		$expected .= '{MockDatabasePostRevision}.{deleted} IS NULL AND ';
		$expected .= '{MockDatabasePost}.{id} = {MockDatabasePostRevision}.';
		$expected .= '{mock_database_post_id};';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testReadWithHasManyAndLimit() {
		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabaseComment'),
			'limit' => 1
		);
		$result = $this->_db->read(new Query($options), $options);
		$this->assertNotInstanceOf('lithium\data\collection\RecordSet', $result);
	}

	public function testGroup() {
		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model
		));
		$result = $this->_db->group(array('id' => 'ASC'), $query);
		$expected = 'GROUP BY {MockDatabasePost}.{id} ASC';
		$this->assertEqual($expected, $result);
	}

	public function testGroupWithAlias() {
		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model, 'alias' => 'MyModel'
		));
		$result = $this->_db->group('id', $query);
		$expected = 'GROUP BY {MyModel}.{id}';
		$this->assertEqual($expected, $result);
	}

	public function testGroupOnRelation() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_comment,
			'with' => 'MockDatabasePost',
			'group' => array('MockDatabaseComment.id')
		));

		$sql = 'SELECT * FROM {mock_database_comments} AS {MockDatabaseComment} LEFT JOIN ';
		$sql .= '{mock_database_posts} AS {MockDatabasePost} ON {MockDatabaseComment}.';
		$sql .= '{mock_database_post_id} = {MockDatabasePost}.{id} GROUP BY ';
		$sql .= '{MockDatabaseComment}.{id};';
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	public function testLimit() {
		MockDatabasePost::find('all', array('limit' => 15));
		$result = $this->_db->sql;
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LIMIT 15;';
		$this->assertEqual($expected, $result);

		MockDatabasePost::find('all', array('limit' => 10, 'page' => 3));
		$result = $this->_db->sql;
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LIMIT 10 OFFSET 20;';
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests that various syntaxes for the `'order'` key of the query object produce the correct
	 * SQL.
	 */
	public function testQueryOrderSyntaxes() {
		$query = new Query(array(
			'type' => 'read', 'model' => $this->_model, 'order' => array('created' => 'ASC')
		));
		$sql = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} ';
		$sql .= 'ORDER BY {MockDatabasePost}.{created} ASC;';
		$this->assertEqual($sql, $this->_db->renderCommand($query));
	}

	/**
	 * Tests that complex model constraints with custom operators render correct constraint strings.
	 */
	public function testRenderArrayJoinConstraintComplex() {
		$model = 'lithium\tests\mocks\data\model\MockQueryComment';

		$query = new Query(compact('model') + array(
			'type' => 'read',
			'source' => 'comments',
			'alias' => 'Comments',
			'conditions' => array('Comment.id' => 1),
			'joins' => array(array(
				'mode' => 'INNER',
				'source' => 'posts',
				'alias' => 'Post',
				'constraints' => array('Comment.post_id' => array('<=' => 'Post.id'))
			))
		));

		$expected = "SELECT * FROM {comments} AS {Comments} INNER JOIN {posts} AS {Post} ON ";
		$expected .= "({Comment}.{post_id} <= {Post}.{id}) WHERE {Comment}.{id} = 1;";
		$result = $this->_db->renderCommand($query);
		$this->assertEqual($expected, $result);
	}

	/**
	 * Tests that complex model constraints with custom operators render correct constraint strings.
	 */
	public function testRenderArrayJoinConstraintComplexArray() {
		$model = 'lithium\tests\mocks\data\model\MockQueryComment';

		$query = new Query(compact('model') + array(
			'type' => 'read',
			'source' => 'comments',
			'alias' => 'Comments',
			'conditions' => array('Comment.id' => 1),
			'joins' => array(array(
				'mode' => 'LEFT',
				'source' => 'posts',
				'alias' => 'Post',
				'constraints' => array(
					"Comment.post_id" => array(
						'<=' => 'Post.id',
						'>=' => 'Post.id'
					)
				)
			))
		));

		$expected = "SELECT * FROM {comments} AS {Comments} LEFT JOIN {posts} AS {Post} ON ";
		$expected .= "({Comment}.{post_id} <= {Post}.{id} AND {Comment}.{post_id} >= {Post}.{id}) ";
		$expected .= "WHERE {Comment}.{id} = 1;";
		$result = $this->_db->renderCommand($query);
		$this->assertEqual($expected, $result);

		$query = new Query(compact('model') + array(
			'type' => 'read',
			'source' => 'comments',
			'alias' => 'Comments',
			'joins' => array(array(
				'mode' => 'LEFT',
				'source' => 'posts',
				'alias' => 'Post',
				'constraints' => array(
					'Comment.post_id' => array('=>' => 'Post.id')
				)
			))
		));
		$this->expectException("Unsupported operator `=>`.");
		$this->_db->renderCommand($query);
	}

	public function testRenderArrayJoin() {
		$model = 'lithium\tests\mocks\data\model\MockQueryComment';

		$query = new Query(compact('model') + array(
			'type' => 'read',
			'source' => 'comments',
			'alias' => 'Comment',
			'conditions' => array('Comment.id' => 1),
			'joins' => array(array(
				'mode' => 'INNER',
				'source' => 'posts',
				'alias' => 'Post',
				'constraints' => array('Comment.post_id' => 'Post.id')
			))
		));

		$expected = "SELECT * FROM {comments} AS {Comment} INNER JOIN {posts} AS {Post} ON ";
		$expected .= "{Comment}.{post_id} = {Post}.{id} WHERE {Comment}.{id} = 1;";
		$result = $this->_db->renderCommand($query);
		$this->assertEqual($expected, $result);
	}

	public function testModelFindBy() {
		$this->_db->log = true;
		MockDatabasePost::findById(5, array('with' => 'MockDatabaseComment'));
		$this->_db->log = false;

		$result = $this->_db->logs[0];
		$expected = "SELECT DISTINCT({MockDatabasePost}.{id}) AS _ID_ FROM {mock_database_posts}";
		$expected .= " AS {MockDatabasePost} LEFT JOIN {mock_database_comments} AS ";
		$expected .= "{MockDatabaseComment} ON {MockDatabasePost}.{id} = ";
		$expected .= "{MockDatabaseComment}.{mock_database_post_id} WHERE ";
		$expected .= "{MockDatabasePost}.{id} = 5 LIMIT 1;";
		$this->assertEqual($expected, $result);
	}

	public function testsplitFieldname() {
		$result = $this->_db->invokeMethod('_splitFieldname', array('Alias.fieldname'));
		$this->assertEqual(array('Alias', 'fieldname'), $result);

		$result = $this->_db->invokeMethod('_splitFieldname', array('fieldname'));
		$this->assertEqual(array(null, 'fieldname'), $result);

		$result = $this->_db->invokeMethod('_splitFieldname', array('fieldname'));
		$this->assertEqual(array(null, 'fieldname'), $result);

		$result = $this->_db->invokeMethod('_splitFieldname', array('lower(Alias.fieldname)'));
		$this->assertEqual(array(null, 'lower(Alias.fieldname)'), $result);

		$result = $this->_db->invokeMethod('_splitFieldname', array('Alias.*'));
		$this->assertEqual(array('Alias', '*'), $result);
	}

	public function testOn() {
		$conn = MockDatabasePost::connection();
		$expected = array(
			'MockDatabasePost.id' => 'MockDatabaseComment.mock_database_post_id'
		);
		$result = $conn->on(MockDatabasePost::relations('MockDatabaseComment'));
		$this->assertEqual($expected, $result);

		$expected = array(
			'MockDatabaseComment.mock_database_post_id' => 'MockDatabasePost.id'
		);
		$result = $conn->on(MockDatabaseComment::relations('MockDatabasePost'));
		$this->assertEqual($expected, $result);

		$expected = array(
			'MockDatabasePost.id' => 'MockDatabaseComment.mock_database_post_id',
			'MockDatabasePost.published' => (object) "'yes'"
		);

		$rel = MockDatabasePost::relations('MockDatabaseComment');
		$result = $conn->on($rel, null, null, array('published' => (object) "'yes'"));

		$this->assertEqual($expected, $result);

		$expected = array(
			'CustomPost.id' => 'CustomComment.mock_database_post_id',
			'CustomPost.published' => (object) "'no'"
		);

		$constraints = array('published' => (object) "'no'");
		$result = $conn->on($rel, 'CustomPost', 'CustomComment', $constraints);
		$this->assertEqual($expected, $result);

		$expected = array(
			'CustomPost.id' => 'CustomComment.post_id'
		);

		$constraints = array('CustomPost.id' => 'CustomComment.post_id');
		$result = $conn->on($rel, 'CustomPost', 'CustomComment', $constraints);
		$this->assertEqual($expected, $result);
	}

	public function testWithGeneration() {
		$model = $this->_gallery;

		$options = array(
			'type' => 'read',
			'model' => $model,
			'with' => array('Image.ImageTag.Tag')
		);

		$result = $this->_db->read(new Query($options));
		$expected = 'SELECT * FROM {mock_gallery} AS {Gallery} LEFT JOIN {mock_image} AS {Image} ';
		$expected .= 'ON {Gallery}.{id} = {Image}.{gallery_id} LEFT JOIN {mock_image_tag} AS ';
		$expected .= '{ImageTag} ON {Image}.{id} = {ImageTag}.{image_id} LEFT JOIN {mock_tag} ';
		$expected .= 'AS {Tag} ON {ImageTag}.{tag_id} = {Tag}.{id};';
		$this->assertEqual($expected, $this->_db->sql);

		$model = $this->_imageTag;
		$options = array(
			'type' => 'read',
			'model' => $model,
			'with' => array('Image', 'Tag')
		);

		$result = $this->_db->read(new Query($options));
		$expected = 'SELECT * FROM {mock_image_tag} AS {ImageTag} LEFT JOIN {mock_image} AS ';
		$expected .= '{Image} ON {ImageTag}.{image_id} = {Image}.{id} LEFT JOIN {mock_tag} AS ';
		$expected .= '{Tag} ON {ImageTag}.{tag_id} = {Tag}.{id};';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testWithOptionAndInlineConstraint() {
		$model = $this->_gallery;

		$options = array(
			'type' => 'read',
			'model' => $model,
			'with' => array(
				'Image' => array(
					'constraints' => array(
						'Image.title' => (object) "'MyImage'"
					)
				),
				'Image.ImageTag.Tag' => array(
					'constraints' => array(
						'Tag.name' => (object) "'MyTag'"
					)
				)
			)
		);
		$result = $this->_db->read(new Query($options));
		$expected = 'SELECT * FROM {mock_gallery} AS {Gallery} ';
		$expected .= 'LEFT JOIN {mock_image} AS {Image} ON {Image}.{title} = \'MyImage\' ';
		$expected .= 'AND {Gallery}.{id} = {Image}.{gallery_id} LEFT JOIN ';
		$expected .= '{mock_image_tag} AS {ImageTag} ON ';
		$expected .= '{Image}.{id} = {ImageTag}.{image_id} LEFT JOIN {mock_tag} AS {Tag} ON ';
		$expected .= '{Tag}.{name} = \'MyTag\' AND {ImageTag}.{tag_id} = {Tag}.{id};';
		$this->assertEqual($expected, $this->_db->sql);

		$to = 'lithium\tests\mocks\data\model\MockImage';
		$model::bind('hasMany', 'Image', array('to' => $to));
		$to::bind('belongsTo', 'Gallery', array('to' => $model));

		$result = $this->_db->read(new Query(array(
			'type' => 'read',
			'model' => $model,
			'with' => array(
				'Image.Gallery' => array(
					'alias' => 'Gallery2',
					'constraints' => array(
						'Gallery.custom_id' => 'Gallery2.id'
					)
				)
			)
		)));
		$expected = 'SELECT * FROM {mock_gallery} AS {Gallery} LEFT JOIN {mock_image} AS {Image}';
		$expected .= ' ON {Gallery}.{id} = {Image}.{gallery_id} LEFT JOIN {mock_gallery} AS ';
		$expected .= '{Gallery2} ON {Gallery}.{custom_id} = {Gallery2}.{id} AND ';
		$expected .= '{Image}.{gallery_id} = {Gallery2}.{id};';
		$this->assertEqual($expected, $this->_db->sql);
		$model::reset();
	}

	public function testWithOptionAndConstraintInRelation() {
		$model = 'lithium\tests\mocks\data\model\MockGallery';
		$to = 'lithium\tests\mocks\data\model\MockImage';
		$model::bind('hasMany', 'Image', array(
			'to' => $to,
			'constraints' => array(
				'Image.title' => (object) "'MyImage'"
			)
		));
		$result = $this->_db->read(new Query(array(
			'type' => 'read',
			'model' => $model,
			'with' => array(
				'Image.ImageTag.Tag' => array(
					'constraints' => array(
						'Tag.name' => (object) "'MyTag'"
					)
				)
			)
		)));

		$expected = 'SELECT * FROM {mock_gallery} AS {Gallery} ';
		$expected .= 'LEFT JOIN {mock_image} AS {Image} ON {Image}.{title} = \'MyImage\' AND ';
		$expected .= '{Gallery}.{id} = {Image}.{gallery_id} LEFT JOIN {mock_image_tag} AS ';
		$expected .= '{ImageTag} ON {Image}.{id} = {ImageTag}.{image_id} LEFT JOIN {mock_tag} AS ';
		$expected .= '{Tag} ON {Tag}.{name} = \'MyTag\' AND {ImageTag}.{tag_id} = {Tag}.{id};';
		$this->assertEqual($expected, $this->_db->sql);

		$model::reset();
	}

	public function testWithOptionWithNullConstraint() {
		$options = array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabasePostRevision')
		);
		$result = $this->_db->read(new Query($options));
		$expected = 'SELECT * FROM {mock_database_posts} AS {MockDatabasePost} LEFT JOIN ';
		$expected .= '{mock_database_post_revisions} AS {MockDatabasePostRevision} ON ';
		$expected .= '{MockDatabasePostRevision}.{deleted} IS NULL AND ';
		$expected .= '{MockDatabasePost}.{id} = {MockDatabasePostRevision}.';
		$expected .= '{mock_database_post_id};';
		$this->assertEqual($expected, $this->_db->sql);
	}

	public function testWithOptionAndCustomAlias() {
		$model = 'lithium\tests\mocks\data\model\MockGallery';

		$model::bind('hasMany', 'Image', array(
			'to' => 'lithium\tests\mocks\data\model\MockImage',
			'constraints' => array(
				'Image.title' => (object) "'MyImage'"
			)
		));

		$options = array(
			'type' => 'read',
			'model' => $model,
			'alias' => 'MyGallery',
			'with' => array(
				'Image' => array('alias' => 'MyImage')
			)
		);

		$result = $this->_db->read(new Query($options));
		$query = new Query($options);
		$expected = 'SELECT * FROM {mock_gallery} AS {MyGallery} LEFT JOIN ';
		$expected .= '{mock_image} AS {MyImage} ON {MyImage}.{title} = \'MyImage\' ';
		$expected .= 'AND {MyGallery}.{id} = {MyImage}.{gallery_id};';
		$this->assertEqual($expected, $this->_db->sql);
		$model::reset();
	}

	public function testJoin() {
		$model =  $this->_model;
		$conn = $model::connection();
		$query = new Query(array('type' => 'read', 'model' => $model));

		$rel = $model::relations('MockDatabaseComment');

		$conn->join($query, $rel, null, null, array('published' => (object) "'yes'"));

		$joins = $query->joins();
		$expected = array(
			'MockDatabaseComment' => array(
				'constraints' => array(
					'MockDatabasePost.id' => 'MockDatabaseComment.mock_database_post_id',
					'MockDatabasePost.published' => (object) "'yes'"
				),
				'model' => 'lithium\tests\mocks\data\model\MockDatabaseComment',
				'mode' => 'LEFT',
				'alias' => 'MockDatabaseComment'
			)
		);

		$this->assertEqual($expected, $joins);

		$query = new Query(array('type' => 'read', 'model' => $model));

		$rel = $model::relations('MockDatabaseComment');

		$conn->join($query, $rel, null, null, (object) array('published' => (object) "'yes'"));

		$joins = $query->joins();
		$expected = array(
			'MockDatabaseComment' => array(
				'constraints' => array(
					'published' => (object) "'yes'"
				),
				'model' => 'lithium\tests\mocks\data\model\MockDatabaseComment',
				'mode' => 'LEFT',
				'alias' => 'MockDatabaseComment'
			)
		);

		$this->assertEqual($expected, $joins);
	}

	public function testExportedFieldsWithJoinedStrategy() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'with' => array('Image.ImageTag.Tag')
		));
		$result = $query->export($this->_db);
		$this->assertEqual('*', $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'id',
			'with' => array('Image.ImageTag.Tag')
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}';
		$this->assertEqual($expected, $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'Tag.id',
			'with' => array('Image.ImageTag.Tag')
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}, {Tag}.{id}, {Image}.{id}, {ImageTag}.{id}';
		$this->assertEqual($expected, $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'Tag',
			'with' => array('Image.ImageTag.Tag')
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}, {Tag}.*, {Image}.{id}, {ImageTag}.{id}';
		$this->assertEqual($expected, $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'Tag.*',
			'with' => array('Image.ImageTag.Tag')
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}, {Tag}.*, {Image}.{id}, {ImageTag}.{id}';
		$this->assertEqual($expected, $result['fields']);
	}

	public function testExportedFieldsWithJoinedStrategyAndRecursiveRelation() {
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'with' => array('Parent.Parent')
		));
		$result = $query->export($this->_db);
		$expected = '*';
		$this->assertEqual($expected, $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'Parent.name',
			'with' => array('Parent.Parent')
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}, {Parent}.{name}';
		$this->assertEqual($expected, $result['fields']);

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => 'ParentOfParent.name',
			'with' => array('Parent.Parent' => array('alias' => 'ParentOfParent'))
		));
		$result = $query->export($this->_db);
		$expected = '{Gallery}.{id}, {ParentOfParent}.{name}, {Parent}.{id}';
		$this->assertEqual($expected, $result['fields']);
	}

	public function testCustomField() {
		$field = "(CASE `title` WHEN 'Lotus Flower' THEN 'Found' ELSE 'Not Found' END) as extra";
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => array('*', $field)
		));
		$result = $this->_db->read($query);
		$expected = 'SELECT (CASE `title` WHEN \'Lotus Flower\' THEN \'Found\' ELSE \'Not Found\' ';
		$expected .= 'END) as extra, {Gallery}.* FROM {mock_gallery} AS {Gallery};';
		$this->assertEqual($expected, $this->_db->sql);
		$map = array('' => array('extra', 'id', 'title'));
		$this->assertEqual($map, $query->map());

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => array('*', (object) $field)
		));
		$result = $this->_db->read($query);
		$this->assertEqual($expected, $this->_db->sql);
		$this->assertEqual($map, $query->map());

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => array('*', array($field))
		));
		$result = $this->_db->read($query);
		$this->assertEqual($expected, $this->_db->sql);
		$this->assertEqual($map, $query->map());

		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_gallery,
			'fields' => array((object) 'count(Image.id) as count', 'Image'),
			'group' => 'Gallery.id',
			'with' => array('Image')
		));
		$result = $this->_db->read($query);
		$expected = 'SELECT count(Image.id) as count, {Gallery}.{id}, {Image}.* FROM ';
		$expected .= '{mock_gallery} AS {Gallery} LEFT JOIN {mock_image} AS {Image} ON ';
		$expected .= '{Gallery}.{id} = {Image}.{gallery_id} GROUP BY {Gallery}.{id};';

		$this->assertEqual($expected, $this->_db->sql);
		$map = array(
			'' => array('count', 'id'),
			'Image' => array('id', 'title', 'image', 'gallery_id')
		);
		$this->assertEqual($map, $query->map());
	}

	public function testReturnArrayOnReadWithString() {
		$data = new MockResult(array('records' => array(
			array ('id', 'int(11)', 'NO', 'PRI', null, 'auto_increment'),
			array ('name', 'varchar(256)', 'YES', '', null, '')
		)));
		$this->_db->return = array(
			'schema' => array('field', 'type', 'null', 'key', 'default', 'extra'),
			'_execute' => $data
		);
		$result = $this->_db->read('DESCRIBE {table};', array('return' => 'array'));
		$expected = array(
			array(
				'field' => 'id',
				'type' => 'int(11)',
				'null' => 'NO',
				'key' => 'PRI',
				'default' => null,
				'extra' => 'auto_increment',
			),
			array(
				'field' => 'name',
				'type' => 'varchar(256)',
				'null' => 'YES',
				'key' => '',
				'default' => null,
				'extra' => '',
			)
		);
		$this->assertEqual($expected, $result);
	}

	public function testReturnArrayOnReadWithQuery() {
		$data = new MockResult(array('records' => array(array(
			'1',
			'2',
			'Post title',
			'2012-12-17 17:04:00',
			'3',
			'1',
			'2',
			'Very good post',
			'2012-12-17 17:05:00',
			'1',
			'2',
			'Post title',
			'2012-12-17 17:04:00',
		))));
		$this->_db->return = array(
			'_execute' => $data
		);
		$query = new Query(array(
			'type' => 'read',
			'model' => $this->_model,
			'with' => array('MockDatabaseComment.MockDatabasePost')
		));
		$result = $this->_db->read($query, array('return' => 'array'));
		$expected = array(array(
			'id' => '1',
			'author_id' => '2',
			'title' => 'Post title',
			'created' => '2012-12-17 17:04:00',
			'MockDatabaseComment' => array(
				'id' => '3',
				'post_id' => '1',
				'author_id' => '2',
				'body' => 'Very good post',
				'created' => '2012-12-17 17:05:00',
				'MockDatabasePost' => array(
					'id' => '1',
					'author_id' => '2',
					'title' => 'Post title',
					'created' => '2012-12-17 17:04:00',
				)
			)
		));
		$this->assertEqual($expected, $result);
	}

	public function testCleanRenderCommand() {
		$entity = new Record(array(
			'model' => $this->_model,
			'data' => array('title' => '{:foobar}'),
			'exists' => false
		));

		$query = new Query(compact('entity') + array('type' => 'create'));
		$result = $this->_db->create($query);

		$expected = "INSERT INTO {mock_database_posts} ({title}) VALUES ('{:foobar}');";
		$this->assertEqual($expected, $this->_db->sql);
	}
}

?>