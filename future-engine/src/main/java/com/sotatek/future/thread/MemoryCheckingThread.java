package com.sotatek.future.thread;

import lombok.extern.slf4j.Slf4j;

import java.lang.management.ManagementFactory;
import java.lang.management.MemoryUsage;

@Slf4j
public class MemoryCheckingThread extends Thread {
    @Override
    public void run() {
        try {
            while (true) {
                MemoryUsage memoryBean = ManagementFactory.getMemoryMXBean().getHeapMemoryUsage();
                log.info("Initial {}", memoryBean.getInit() / (1024 * 1024));
                log.info("Using {}", memoryBean.getUsed() / (1024 * 1024));
                log.info("Max {}", memoryBean.getMax() / (1024 * 1024));
                Thread.sleep(2000);
            }
        } catch (Exception e) {
            log.error(e.getMessage(), e);
        }
    }
}
