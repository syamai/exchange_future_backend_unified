import { OrderEntity } from "src/models/entities/order.entity";
import { CreateOrderDto } from "../dto/create-order.dto";
import { IUserAccount } from "../interface/account-user.interface";

export class SaveOrderFromClientCommand {
    public createOrderDto: CreateOrderDto;
    public accountData: IUserAccount;
    public tmpOrder: any;
}

export class SaveOrderFromClientCommandV2 {
    public createOrderDto: CreateOrderDto;
    public userId: number;
    public tmpOrderId: string;
}