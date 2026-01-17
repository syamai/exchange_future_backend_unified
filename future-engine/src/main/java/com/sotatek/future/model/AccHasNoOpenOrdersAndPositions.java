package com.sotatek.future.model;
import lombok.*;

@Getter
@Setter
@ToString
@NoArgsConstructor
@AllArgsConstructor
@Builder
public class AccHasNoOpenOrdersAndPositions {
    private Long accountId;
    private Long userId;
}
