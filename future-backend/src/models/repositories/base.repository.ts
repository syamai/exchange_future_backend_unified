import { Repository } from "typeorm";

export class BaseRepository<T extends { id: number }> extends Repository<T> {
  async insertOrUpdate(entities: T[]): Promise<void> {
    if (entities.length == 0) {
      return;
    }
    
    let tableName: string;
    try {
      tableName = this.getTableName(entities[0].constructor.name);
    } catch (error) {
      console.error('Error getting table name:', error);
      console.error('entities: ', JSON.stringify(entities));
      return;
    }
    
    const columns = this.getColumns(entities[0].constructor.name);
    
    if (tableName === 'accounts') {
      const index = columns.findIndex(colName => colName === "rewardBalance");      
      if (index !== -1) {
        columns.splice(index, 1); // Removes the rewardBalance col at this index
      }
    }

    const quotedColumns = columns.map((column) => `\`${column}\``);
    const columnsString = quotedColumns.join(", ");
    const placeholder = new Array(columns.length).fill("?");
    const placeholders = new Array(entities.length)
      .fill(`(${placeholder})`)
      .join(", ");
    //TODO: check leverage
    const valueString = [];
    for (const column of quotedColumns) {
      if (
        column !== "`operationId`" &&
        (tableName === "positions" || tableName === "orders")
      ) {
        valueString.push(
          `${column} = IF(VALUES(operationId) >= operationId, VALUES(${column}), ${column})`
        );
      } else if (column !== "`operationId`") {
        valueString.push(
          `${column} = IF(VALUES(operationId) > operationId, VALUES(${column}), ${column})`
        );
      }
    }
    valueString.push(
      "`operationId` = IF(VALUES(operationId) > operationId, VALUES(`operationId`), `operationId`);"
    );
    // const valueString = quotedColumns.map((column) => {
    //   if (
    //     column === '`isHidden`' ||
    //     column === '`linkedOrderId`' ||
    //     column === '`leverage`' ||
    //     column === '`cost`' ||
    //     column === '`parentOrderId`' ||
    //     column === '`adjustMargin`' ||
    //     column === '`takeProfitOrderId`' ||
    //     column === '`stopLossOrderId`' ||
    //     column === '`userId`' ||
    //     column === '`updatedAt`' ||
    //     (column === '`status`' && tableName === 'orders')
    //   ) {
    //     // handle case the matching engine sends multiple data in a event (At this moment operationId in a event is the same)
    //     return `${column} = IF(VALUES(operationId) >= operationId, VALUES(${column}), ${column})`;
    //   } else {
    //     return `${column} = IF(VALUES(operationId) > operationId, VALUES(${column}), ${column})`;
    //   }
    // });
    let sql = "";
    sql += `INSERT INTO \`${tableName}\` (${columnsString})`;
    sql += ` VALUES ${placeholders}`;
    sql += ` ON DUPLICATE KEY UPDATE ${valueString}`;
    const params = [];

    for (const entity of entities) {
      for (const column of columns) {
        params.push(entity[column]);
      }
    }
    await this.manager.query(sql, params);
  }

  public async findBatch(fromId: number, count: number): Promise<T[]> {
    return this.createQueryBuilder()
      .where("id > :fromId", { fromId })
      .orderBy("id", "ASC")
      .take(count)
      .getMany();
  }

  public async getLastId(): Promise<number> {
    const order = {};
    order["id"] = "DESC";
    const entity = await this.findOne({ order, select: ["id"] });
    if (entity) {
      return entity.id;
    } else {
      return 0;
    }
  }

  protected getColumns(target: string): string[] {
    const queryBuilder = this.createQueryBuilder();
    return queryBuilder.connection
      .getMetadata(target)
      .columns.map((column) => column.propertyName);
  }

  protected getTableName(target: string): string {
    const queryBuilder = this.createQueryBuilder();
    return queryBuilder.connection.getMetadata(target).tableName;
  }
}
