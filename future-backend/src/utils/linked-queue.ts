class LinkedQueueNode<T> {
  value: T;
  next: LinkedQueueNode<T> | null = null;

  constructor(value: T) {
    this.value = value;
  }
}

export class LinkedQueue<T> {
  private head: LinkedQueueNode<T> | null = null;
  private tail: LinkedQueueNode<T> | null = null;
  private count: number = 0;

  enqueue(value: T): void {
    const newNode = new LinkedQueueNode(value);
    if (this.tail) {
      this.tail.next = newNode;
    }
    this.tail = newNode;
    if (!this.head) {
      this.head = newNode;
    }
    this.count++;
  }

  dequeue(): T | undefined {
    if (!this.head) return undefined;
    const value = this.head.value;
    const next = this.head.next;
    delete this.head;
    this.head = next;

    if (!this.head) {
      delete this.tail;
    }
    this.count--;
    return value;
  }

  peek(): T | undefined {
    return this.head?.value;
  }

  isEmpty(): boolean {
    return this.count === 0;
  }

  size(): number {
    return this.count;
  }
}
