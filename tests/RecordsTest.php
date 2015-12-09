<?php

class RecordsTest extends TestBase {

	public function setUp() {
		parent::setUp();
		// Let the current user do anything.
		global $current_user;
		$current_user->add_cap( 'promote_users' );
	}

	/**
	 * @testdox Getting the *FKTITLE() variant of a foreign key returns the title of the foreign record.
	 * @test
	 */
	public function related() {
		$test_table = $this->db->get_table( 'test_table' );
		$typeRec = $this->db->get_table( 'test_types' )->save_record( array( 'title' => 'Type 1' ) );
		$dataRec = $test_table->save_record( array( 'title' => 'Rec 1', 'type_id' => $typeRec->id() ) );
		$this->assertEquals( 'Type 1', $dataRec->type_idFKTITLE() );
		$referecingRecs = $typeRec->get_referencing_records( $test_table, 'type_id' );
		$this->assertCount( 1, $referecingRecs );
		$referecingRec = array_pop( $referecingRecs );
		$this->assertEquals( 'Rec 1', $referecingRec->title() );
	}

	/**
	 * @testdox Where there is no unique column, the 'title' is just the foreign key.
	 * @test
	 */
	public function titles() {
		$test_table = $this->db->get_table( 'test_table' );
		$this->assertEmpty( $test_table->get_unique_columns() );
		$this->assertEquals( 'id', $test_table->get_title_column()->getName() );
		$rec = $test_table->save_record( array( 'title' => 'Rec 1', 'description' => 'Lorem ipsum.' ) );
		$this->assertEquals( '[ 1 | Rec 1 | Lorem ipsum. | 1 |  |  |  | 5.60 |  |  ]', $rec->getTitle() );
	}

	/**
	 * @testdox
	 * @test
	 */
	public function record_counts() {
		$test_table = $this->db->get_table( 'test_table' );

		// Initially empty.
		$this->assertEquals( 0, $test_table->count_records() );

		// Add one.
		$rec1 = $test_table->save_record( array( 'title' => 'Rec 1', 'description' => 'Testing.' ) );
		$this->assertEquals( 1, $test_table->count_records() );

		// Add 2.
		$test_table->save_record( array( 'title' => 'Rec 2' ) );
		$test_table->save_record( array( 'title' => 'Rec 3' ) );
		$this->assertEquals( 3, $test_table->count_records() );

		// Add 50.
		for ( $i = 0; $i < 50; $i ++ ) {
			$test_table->save_record( array( 'title' => "Record $i" ) );
		}
		$this->assertEquals( 53, $test_table->count_records() );

		// Make sure it still works with filters applied.
		$test_table->add_filter( 'title', 'like', 'Record' );
		$this->assertEquals( 50, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'like', 'Testing' );
		$this->assertEquals( 1, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'not empty', '' );
		$this->assertEquals( 1, $test_table->count_records() );
		$test_table->reset_filters();
		$test_table->add_filter( 'description', 'empty', '' );
		$this->assertEquals( 52, $test_table->count_records() );
		$test_table->reset_filters();

		// Delete a record.
		$test_table->delete_record( $rec1->id() );
		$this->assertEquals( 52, $test_table->count_records() );
	}

	/**
	 * @testdox Cache the record counts for base tables which have no filters applied.
	 * @test
	 */
	public function count_store_some() {
		$test_table = $this->db->get_table( 'test_table' );
		// Add some records.
		for ( $i = 0; $i < 50; $i ++ ) {
			$test_table->save_record( array( 'title' => "Record $i" ) );
		}
		$this->assertEquals( 50, $test_table->count_records() );
		$transient_name = TABULATE_SLUG . '_test_table_count';
		$this->assertEquals( 50, get_transient( $transient_name ) );
		// With filters applied, the transient value shouldn't change.
		$test_table->add_filter( 'title', 'like', 'Record 1' );
		$this->assertEquals( 11, $test_table->count_records() );
		$this->assertEquals( 50, get_transient( $transient_name ) );
	}

	/**
	 * Bug description: when editing a record, and a filter has been applied to
	 * a referenced table, the count is the *filtered* count (and thus
	 * incorrect).
	 * @test
	 */
	public function count_related() {
		// Create 50 types.
		$types_normal = $this->db->get_table( 'test_types' );
		for ( $i = 0; $i < 50; $i ++ ) {
			$types_normal->save_record( array( 'title' => "Type $i" ) );
		}
		$this->assertEquals( 50, $types_normal->count_records() );

		// The test_table should know there are 50.
		$test_table = $this->db->get_table( 'test_table' );
		$type_col = $test_table->get_column( 'type_id' );
		$referenced_tables = $test_table->get_referenced_tables( true );
		$types_referenced = $referenced_tables['type_id'];
		$this->assertNotSame( $types_normal, $types_referenced );
		$this->assertCount( 0, $types_normal->get_filters() );
		$this->assertCount( 0, $types_referenced->get_filters() );
		$this->assertEquals( 50, $types_normal->count_records() );
		$this->assertEquals( 50, $types_referenced->count_records() );
		$this->assertEquals( 50, $type_col->get_referenced_table()->count_records() );

		// Now apply a filter to the test_types table.
		$types_normal->add_filter( 'title', 'like', '20' );
		$this->assertCount( 1, $types_normal->get_filters() );
		$this->assertCount( 0, $types_referenced->get_filters() );
		$this->assertEquals( 1, $types_normal->count_records() );
		$this->assertEquals( 50, $types_referenced->count_records() );
		$this->assertEquals( 1, $type_col->get_referenced_table()->count_records() );
	}

}
