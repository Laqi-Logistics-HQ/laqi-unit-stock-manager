<?php
/** Premium supply-state tests. @package LaqiUnitStockManager */
use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Reservations\ReservationRepository;
use LaqiUnitStockManager\Premium\Supply\StockHoldRepository;
use LaqiUnitStockManager\Premium\Supply\StockHoldService;
use LaqiUnitStockManager\Storage\Schema;
/** Verifies quarantined and damaged quantities remain exact and auditable. */
class Test_Stock_Holds extends WP_UnitTestCase {
	/** @var StockHoldRepository */ private $holds; /** @var StockHoldService */ private $service; /** @var int */ private $pool_id;
	/** Install. */ public static function set_up_before_class():void{parent::set_up_before_class();Schema::install();global $wpdb;delete_option(ReservationRepository::SCHEMA_OPTION);(new ReservationRepository($wpdb))->install();delete_option(StockHoldRepository::SCHEMA_OPTION);(new StockHoldRepository($wpdb))->install();}
	/** Fixture. */ public function set_up():void{parent::set_up();global $wpdb;$wpdb->query('DELETE FROM '.Schema::table('stock_holds'));$container=new Container();$this->holds=new StockHoldRepository($wpdb);$this->service=new StockHoldService($this->holds,$container->pool_repository(),$container->stock_mutation_service());$this->pool_id=$container->pool_repository()->create('Held stock '.wp_generate_uuid4(),new Quantity('count',10),'each','each')->id();}
	/** Cleanup. */ public function tear_down():void{global $wpdb;$wpdb->delete(Schema::table('reservations'),array('pool_id'=>$this->pool_id),array('%d'));$wpdb->delete(Schema::table('stock_holds'),array('pool_id'=>$this->pool_id),array('%d'));$wpdb->delete(Schema::table('movements'),array('pool_id'=>$this->pool_id),array('%d'));$wpdb->delete(Schema::table('pools'),array('id'=>$this->pool_id),array('%d'));parent::tear_down();}
	/** Holds reduce ATS without changing on-hand. */ public function test_quarantine_and_damage_reduce_available_to_sell():void{$this->service->place($this->pool_id,'quarantined',3,'Inspection',7);$this->service->place($this->pool_id,'damaged',2,'Crushed',7);$this->assertSame(5,$this->holds->held_quantity($this->pool_id));$this->assertSame(5,$this->service->available_quantity(10,$this->pool_id));global $wpdb;$this->assertSame('10',$wpdb->get_var($wpdb->prepare('SELECT quantity_base FROM '.Schema::table('pools').' WHERE id=%d',$this->pool_id)));}
	/** Active reservations and holds share one atomic capacity. */ public function test_holds_cannot_oversell_reserved_supply():void{global $wpdb;(new ReservationRepository($wpdb))->reserve(700001,array($this->pool_id=>7),gmdate('Y-m-d H:i:s',time()+HOUR_IN_SECONDS));$this->expectException(InvalidArgumentException::class);$this->service->place($this->pool_id,'quarantined',4,'Too much',7);}
	/** Releasing restores ATS; write-off reduces physical stock exactly once. */ public function test_release_and_writeoff_lifecycle():void{$released=$this->service->place($this->pool_id,'quarantined',3,'Check',7);$this->service->release($released);$this->assertSame(0,$this->holds->held_quantity($this->pool_id));$damaged=$this->service->place($this->pool_id,'damaged',2,'Broken',7);$this->service->write_off($damaged,7);$this->service->write_off($damaged,7);global $wpdb;$this->assertSame('8',$wpdb->get_var($wpdb->prepare('SELECT quantity_base FROM '.Schema::table('pools').' WHERE id=%d',$this->pool_id)));$this->assertSame('written_off',$wpdb->get_var($wpdb->prepare('SELECT status FROM '.Schema::table('stock_holds').' WHERE id=%d',$damaged)));}
}
