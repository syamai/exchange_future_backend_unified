import { PartialType } from "@nestjs/swagger";
import { CreateInstrumentDto } from "src/modules/instrument/dto/create-instrument.dto";

export class UpdateInstrumentDto extends PartialType(CreateInstrumentDto) {}
