import {MigrationInterface, QueryRunner} from "typeorm";

export class AddOrderMarginIndexes1770439874006 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<void> {
        // Composite index for calOrderMargin query optimization
        // Query: WHERE accountId = ? AND asset = ? AND status IN (...)
        await queryRunner.query(`
            CREATE INDEX idx_orders_margin_calc
            ON orders (accountId, asset, status)
        `);

        // Secondary index for account + status queries
        await queryRunner.query(`
            CREATE INDEX idx_orders_account_status
            ON orders (accountId, status)
        `);
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query(`DROP INDEX idx_orders_margin_calc ON orders`);
        await queryRunner.query(`DROP INDEX idx_orders_account_status ON orders`);
    }

}
