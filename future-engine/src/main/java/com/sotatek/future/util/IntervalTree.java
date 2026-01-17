package com.sotatek.future.util;

import static java.lang.Math.max;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import org.jetbrains.annotations.VisibleForTesting;

/**
 * An AVL interval tree for fast interval search
 *
 * @param <T>
 * @param <K>
 */
public class IntervalTree<T extends Comparable, K extends Interval<T>> {

  private Node base = null;

  public IntervalTree<T, K> insert(K interval) {
    base = recursiveInsert(base, interval);
    return this;
  }

  public List<K> lookup(T point) {
    List<K> result = new ArrayList<>();
    recursiveSearch(base, point, result);
    return Collections.unmodifiableList(result);
  }

  @VisibleForTesting
  boolean isBalance() {
    int baseBalance = getBalance(base);
    return baseBalance >= -1 && baseBalance <= 1;
  }

  private void recursiveSearch(Node node, T point, List<K> results) {
    // Point is empty
    if (node == null) {
      return;
    }
    // Point larger than max
    if (point.compareTo(node.max) > 0) {
      return;
    }

    // Search the left tree
    recursiveSearch(node.left, point, results);

    // Search current node
    if (contains(node.range, point)) {
      results.add(node.range);
    }
    // Point smaller than current interval
    if (point.compareTo(node.range.low()) < 0) {
      return;
    }

    // Search the right ree
    recursiveSearch(node.right, point, results);
  }

  private boolean contains(Interval<T> interval, T point) {
    if (point == null) {
      return false;
    }
    return point.compareTo(interval.low()) > 0 && point.compareTo(interval.high()) <= 0;
  }

  private Node recursiveInsert(Node node, K interval) {
    if (node == null) {
      return new Node(interval, interval.high());
    }
    // Duplicate, overwrite
    if (interval.low().compareTo(node.range.low()) == 0
        && interval.high().compareTo(node.range.high()) == 0) {
      node.range = interval;
      return node;
    }

    if (interval.low().compareTo(node.range.low()) < 0) {
      node.left = recursiveInsert(node.left, interval);
    } else {
      node.right = recursiveInsert(node.right, interval);
    }
    if (node.max.compareTo(interval.high()) < 0) {
      node.max = interval.high();
    }
    // Re-balance tree
    node.height = 1 + max(height(node.left), height(node.right));
    int balance = getBalance(node);

    // Left-left imbalance
    if (balance > 1 && interval.high().compareTo(node.left.range.low()) < 0) {
      return rightRotate(node);
    }

    // Right-Right imbalance
    if (balance < -1 && interval.low().compareTo(node.right.range.high()) > 0) {
      return leftRotate(node);
    }

    // Left-Right imbalance
    if (balance > 1 && interval.low().compareTo(node.left.range.high()) > 0) {
      node.left = leftRotate(node.left);
      return rightRotate(node);
    }

    // Right-Left imbalance
    if (balance < -1 && interval.high().compareTo(node.right.range.low()) < 0) {
      node.right = rightRotate(node.right);
      return leftRotate(node);
    }

    return node;
  }

  private int height(Node n) {
    if (n == null) return 0;

    return n.height;
  }

  private int getBalance(Node n) {
    if (n == null) return 0;

    return height(n.left) - height(n.right);
  }

  // A utility function to right rotate subtree rooted with y
  private Node rightRotate(Node y) {
    Node x = y.left;
    Node t2 = x.right;

    // Perform rotation
    x.right = y;
    y.left = t2;

    // Update heights
    y.height = max(height(y.left), height(y.right)) + 1;
    x.height = max(height(x.left), height(x.right)) + 1;

    // Update y max
    if (t2 != null && y.range.high().compareTo(t2.max) < 0) {
      y.max = t2.max;
    } else {
      y.max = y.range.high();
    }
    // Return new root
    return x;
  }

  // A utility function to left rotate subtree rooted with x
  // See the diagram given above.
  private Node leftRotate(Node x) {
    Node y = x.right;
    Node t2 = y.left;

    // Perform rotation
    y.left = x;
    x.right = t2;

    //  Update heights
    x.height = max(height(x.left), height(x.right)) + 1;
    y.height = max(height(y.left), height(y.right)) + 1;

    // Update x max
    if (t2 != null && x.range.high().compareTo(t2.max) < 0) {
      x.max = t2.max;
    } else {
      x.max = x.range.high();
    }
    // Return new root
    return y;
  }

  class Node {
    K range;
    private Node left;
    private Node right;

    T max;

    int height;

    public Node(K range, T max) {
      this.range = range;
      this.max = max;
    }
  }
}
